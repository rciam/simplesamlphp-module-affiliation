<?php

namespace SimpleSAML\Module\affiliation\Auth\Process;

use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\XHTML\Template;
use SimpleSAML\Utils\Config;
use SimpleSAML\Metadata;

/**
 * SimpleSAMLphp authproc filter for extracting the user's primary affiliation.
 *
 * The filter generates a primary affiliation (eduPersonPrimaryAffiliation)
 * value based on the affiliation information contained in the scoped
 * affiliation attribute (eduPersonScopedAffiliation - ePSA) value(s).
 * Specifically, in the presence of a single-valued ePSA attribute,
 * the primary affiliation is derived from the affiliation value contained in
 * that ePSA attribute. In the case of a multi-valued ePSA attribute, the
 * filter assigns the "member" affiliation for one or more of the following
 * affiliations:
 *   - "faculty" or
 *   - "staff" or
 *   - "student" or
 *   - "employee"
 *
 * The module assumes that:
 *  1. the eduPersonPrimaryAffiliation attribute name is expressed as:
 *     "urn:oid:1.3.6.1.4.1.5923.1.1.1.5"
 *
 * Example configuration:
 *
 *    'authproc' => [
 *        ...
 *        '101' => [
 *            'class' => 'affiliation:PrimaryAffiliation',
 *            // Optional value for scopedAffiliation, defaults to 'eduPersonScopedAffiliation'
 *            'scopedAffiliation' => 'voPersonExternalAffiliation',
 *            // Optional value for oAttribute, defaults to 'urn:oid:2.5.4.10'
 *            'oAttribute' => 'o',
 *            // Optional list of SP entity IDs that should be excluded
 *            'blacklist' => [
 *                'https://sp1.example.org',
 *                'https://sp2.example.org',
 *            ],
 *        ],
 *    ],
 *
 * @author Nicolas Liampotis <nliam@grnet.gr>
 */
class PrimaryAffiliation extends \SimpleSAML\Auth\ProcessingFilter
{
    private $scopedAffiliation = 'eduPersonScopedAffiliation';

    // o (organizationName), (defined in RFC4519; urn:oid:2.5.4.10
    // schacHomeOrganization, urn:oid:1.3.6.1.4.1.25178.1.2.9
    private $oAttribute = 'urn:oid:2.5.4.10';
    private $affiliationAttribute = 'urn:oid:1.3.6.1.4.1.5923.1.1.1.5';

    // List of SP entity IDs that should be excluded from this filter.
    private $blacklist = [];

    // List of IdP entity IDs that should be excluded from this filter.
    private $idpBlacklist = array();

    private $memberAffiliations = [
        'faculty',
        'staff',
        'student',
        'employee',
        'member',
    ];

    public function __construct($config, $reserved)
    {
        parent::__construct($config, $reserved);
        assert('is_array($config)');

        if (array_key_exists('blacklist', $config)) {
            if (!is_array($config['blacklist'])) {
                Logger::error(
                    "[affiliation] Configuration error: 'blacklist' not an array");
                throw new \Exception(
                    "affiliation configuration error: 'blacklist' not an array");
            }
            $this->blacklist = $config['blacklist'];
        }

        if (array_key_exists('scopedAffiliation', $config)) {
            if (!is_string($config['scopedAffiliation'])) {
                Logger::error(
                    "[affiliation] Configuration error: 'scopedAffiliation' not a string");
                throw new \Exception(
                    "affiliation configuration error: 'scopedAffiliation' not a string");
            }
            $this->scopedAffiliation = $config['scopedAffiliation'];
        }

        if (array_key_exists('oAttribute', $config)) {
            if (!is_string($config['oAttribute'])) {
              Logger::error(
                "[affiliation] Configuration error: 'oAttribute' not a string literal");
              throw new \Exception(
               "attrauthcomanage configuration error: 'oAttribute' not a string literal");
            }
            $this->oAttribute = $config['oAttribute'];
        }
    }

    public function process(&$state)
    {
        try {
            // Skip blacklisted SPs
            assert('is_array($state)');
            if (isset($state['SPMetadata']['entityid']) && in_array($state['SPMetadata']['entityid'], $this->blacklist, true)) {
                Logger::debug(
                    "[affiliation] process: Skipping blacklisted SP "
                    . var_export($state['SPMetadata']['entityid'], true));
                return;
            }

          // If the module is active on a bridge $state['saml:sp:IdP']
          // will contain an entry id for the remote IdP.
          if (!empty($state['saml:sp:IdP'])) {
            $idpEntityId = $state['saml:sp:IdP'];
            $idpMetadata = Metadata\MetaDataStorageHandler::getMetadataHandler()->getMetaData($idpEntityId, 'saml20-idp-remote');
          } else {
            $idpEntityId = $state['Source']['entityid'];
            $idpMetadata = $state['Source'];
          }


          // XXX Try to extract Membership and Organization from scopedAffiliation
          if (isset($state['Attributes'][$this->scopedAffiliation])
              && is_array($state['Attributes'][$this->scopedAffiliation])
          ) {
            foreach ($state['Attributes'][$this->scopedAffiliation] as $epsa) {
                if (strpos($epsa, "@") === false) {
                    continue;
                }
                [$foundAffiliation, $shachHomeOrganization] = explode("@", $epsa, 2);
                $state['Attributes'][$this->oAttribute] = [ $shachHomeOrganization ];
                if (in_array($foundAffiliation, $this->memberAffiliations)) {
                    $state['Attributes'][$this->affiliationAttribute] = ['member'];
                    break;
                } else {
                    $state['Attributes'][$this->affiliationAttribute] = [$foundAffiliation];
                }
                Logger::debug("[affiliation] updated attributes="
                              . var_export($state['Attributes'], true));
                return;
            }
          }

          // XXX This IdP is blacklisted. Skip the process of digging th organization name
          if (in_array($idpEntityId, $this->idpBlacklist, true)) {
            Logger::debug(
              "[affiliation] process: Skipping blacklisted IdP "
              . var_export($idpEntityId, true));
            return;
          }

          // XXX We do not need the attribute list nor the attribute blacklist
          //     since we refer specific to organization related attributes
          // Fetch the Organization Friendly name from the Metadata
          $oValue = $this->getO($idpMetadata);
          if (!empty($oValue)) {
            Logger::debug(
              "[affiliation] process: Found o in IdP metadata "
              . var_export($oValue, true));
            // Configured O Attribute
            $state['Attributes'][$this->oAttribute] = [$oValue];
            // By default we set the affiliation to member
            $state['Attributes'][$this->affiliationAttribute] = ['member'];
          }

            Logger::debug("[affiliation] updated attributes="
                . var_export($state['Attributes'], true));
        } catch (\Exception $e) {
            $this->showException($e);
        }
    }

    private function getO($metadata) {
      if (isset($metadata['UIInfo']['DisplayName'])) {
        $displayName = $metadata['UIInfo']['DisplayName'];
        assert('is_array($displayName)'); // Should always be an array of language code -> translation
        if (!empty($displayName['en'])) {
          return $displayName['en'];
        }
      }

      if (array_key_exists('name', $metadata)) {
        if (is_array($metadata['name']) && !empty($metadata['name']['en'])) {
          return $metadata['name']['en'];
        } else {
          return $metadata['name'];
        }
      }

      return null;
  }

    private function showException($e)
    {
        $globalConfig = Configuration::getInstance();
        $t = new Template($globalConfig, 'affiliation:exception.tpl.php');
        $t->data['e'] = $e->getMessage();
        $t->show();
        exit();
    }
}
