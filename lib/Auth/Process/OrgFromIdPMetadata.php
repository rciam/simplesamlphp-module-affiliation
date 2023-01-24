<?php

namespace SimpleSAML\Module\affiliation\Auth\Process;

use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\XHTML\Template;
use SimpleSAML\Utils\Config;
use SimpleSAML\Metadata;

/**
 * SimpleSAMLphp authproc filter for extracting Organization friendly Name from IdP Metadata.
 *
 * The filter fetches the Identity Provider Organization friendly name and assigns it to the
 * configured Organization attribute when the attribute is empty.
 *
 *
 * Example configuration:
 *
 *    'authproc' => [
 *        ...
 *        '101' => [
 *            'class' => 'affiliation:OrgFromIdPMetadata',
 *            // Optional value for oAttribute, defaults to 'urn:oid:2.5.4.10'
 *            'oAttribute' => 'o',
 *            // Optional list of SP entity IDs that should be excluded
 *            'spBlacklist' => [
 *                'https://sp1.example.org',
 *                'https://sp2.example.org',
 *            ],
 *           'idpBlacklist' => [
 *                'https://idp1.example.org',
 *                'https://idp2.example.org',
 *            ],
 *        ],
 *    ],
 *
 * @author Ioannis Igoumenos <ioigoume@admin.grnet.gr>
 */
class OrgFromIdPMetadata extends \SimpleSAML\Auth\ProcessingFilter
{
  // o (organizationName), (defined in RFC4519; urn:oid:2.5.4.10
  // schacHomeOrganization, urn:oid:1.3.6.1.4.1.25178.1.2.9
  private $oAttribute = 'urn:oid:2.5.4.10';

  // List of SP entity IDs that should be excluded from this filter.
  private $spBlacklist = [];

  // List of IdP entity IDs that should be excluded from this filter.
  private $idpBlacklist = [];

  public function __construct($config, $reserved)
  {
    parent::__construct($config, $reserved);
    assert('is_array($config)');

    if (array_key_exists('spBlacklist', $config)) {
      if (!is_array($config['spBlacklist'])) {
        Logger::error(
          "[OrgFromIdPMetadata] Configuration error: 'spBlacklist' not an array");
        throw new \Exception(
          "affiliation configuration error: 'spBlacklist' not an array");
      }
      $this->spBlacklist = $config['spBlacklist'];
    }

    if (array_key_exists('idpBlacklist', $config)) {
      if (!is_array($config['idpBlacklist'])) {
        Logger::error(
          "[OrgFromIdPMetadata] Configuration error: 'idpBlacklist' not an array");
        throw new \Exception(
          "affiliation configuration error: 'idpBlacklist' not an array");
      }
      $this->idpBlacklist = $config['idpBlacklist'];
    }

    if (array_key_exists('oAttribute', $config)) {
      if (!is_string($config['oAttribute'])) {
        Logger::error(
          "[OrgFromIdPMetadata] Configuration error: 'oAttribute' not a string literal");
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
      if (isset($state['SPMetadata']['entityid']) && in_array($state['SPMetadata']['entityid'], $this->spBlacklist, true)) {
        Logger::debug(
          "[OrgFromIdPMetadata] process: Skipping blacklisted SP "
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

      // Skip blacklisted IdP
      if (in_array($idpEntityId, $this->idpBlacklist, true)) {
        Logger::debug(
          "[OrgFromIdPMetadata] process: Skipping blacklisted IdP "
          . var_export($idpEntityId, true));
        return;
      }

      if(!empty($state['Attributes'][$this->oAttribute])) {
        Logger::debug(
          "[OrgFromIdPMetadata] process: {$this->oAttribute} Attribute already exists in state.");
        return;
      }

      // Fetch the Organization Friendly name from the Metadata
      $oValue = $this->getO($idpMetadata);
      if (!empty($oValue)) {
        Logger::debug(
          "[OrgFromIdPMetadata] process: Found o in IdP metadata "
          . var_export($oValue, true));
        // Configured O Attribute
        $state['Attributes'][$this->oAttribute] = [$oValue];
      }

      Logger::debug("[OrgFromIdPMetadata] updated attributes="
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
