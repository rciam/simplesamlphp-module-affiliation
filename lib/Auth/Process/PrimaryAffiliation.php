<?php

namespace SimpleSAML\Module\affiliation\Auth\Process;

use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\XHTML\Template;
use SimpleSAML\Utils\Config;

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

    // List of SP entity IDs that should be excluded from this filter.
    private $blacklist = [];
    
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
                    "[affiliation] Configuration error: 'scopedAffiliation' not an string");
                throw new \Exception(
                    "affiliation configuration error: 'scopedAffiliation' not an string");
            }
            $this->scopedAffiliation = $config['scopedAffiliation']; 
        }
    }

    public function process(&$state)
    {
        try {
            assert('is_array($state)');
            if (isset($state['SPMetadata']['entityid']) && in_array($state['SPMetadata']['entityid'], $this->blacklist, true)) {
                Logger::debug(
                    "[affiliation] process: Skipping blacklisted SP "
                    . var_export($state['SPMetadata']['entityid'], true));
                return;
            }
            if (empty($state['Attributes'][$this->scopedAffiliation])) {
                Logger::debug(
                    "[affiliation] '". $this->scopedAffiliation . "' attribute not available - skipping");
                return;
            }
            foreach ($state['Attributes'][$this->scopedAffiliation] as $epsa) {
                if (strpos($epsa, "@") === false) {
                    continue;    
                }
                $epsaArray = preg_split("~@~", $epsa, 2);
                $foundAffiliation = $epsaArray[0];
                $state['Attributes']['urn:oid:1.3.6.1.4.1.25178.1.2.9'] = [$epsaArray[1]];
                if (in_array($foundAffiliation, $this->memberAffiliations)) {
                    $state['Attributes']['urn:oid:1.3.6.1.4.1.5923.1.1.1.5'] = ['member'];
                    break;
                } else {
                    $state['Attributes']['urn:oid:1.3.6.1.4.1.5923.1.1.1.5'] = [$foundAffiliation];
                }
            }
            Logger::debug("[affiliation] updated attributes="
                . var_export($state['Attributes'], true));
        } catch (\Exception $e) {
            $this->showException($e);
        }
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
