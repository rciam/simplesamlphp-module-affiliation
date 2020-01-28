<?php

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
 *  1. the eduPersonScopedAffiliation attribute name is expressed as:
 *     "urn:oid:1.3.6.1.4.1.5923.1.1.1.9"
 *  2. the eduPersonPrimaryAffiliation attribute name is expressed as:
 *     "urn:oid:1.3.6.1.4.1.5923.1.1.1.5"
 *
 * Example configuration:
 *
 *    'authproc' => array(
 *        ...
 *        '101' => array(
 *            'class' => 'affiliation:PrimaryAffiliation',
 *            // Optional list of SP entity IDs that should be excluded
 *            'blacklist' => array(
 *                'https://sp1.example.org',
 *                'https://sp2.example.org',
 *            ),
 *        ),
 *    ),
 *
 * @author Nicolas Liampotis <nliam@grnet.gr>
 */
class sspmod_affiliation_Auth_Process_PrimaryAffiliation extends SimpleSAML_Auth_ProcessingFilter
{
    // List of SP entity IDs that should be excluded from this filter.
    private $blacklist = array();
    
    private $memberAffiliations = array(
        'faculty',
        'staff',
        'student',
        'employee',
        'member',
    );

    public function __construct($config, $reserved)
    {
        parent::__construct($config, $reserved);
        assert('is_array($config)');

        if (array_key_exists('blacklist', $config)) {
            if (!is_array($config['blacklist'])) {
                SimpleSAML_Logger::error(
                    "[attrauthcomanage] Configuration error: 'blacklist' not an array");
                throw new SimpleSAML_Error_Exception(
                    "attrauthcomanage configuration error: 'blacklist' not an array");
            }
            $this->blacklist = $config['blacklist']; 
        }
    }

    public function process(&$state)
    {
        try {
            assert('is_array($state)');
            if (isset($state['SPMetadata']['entityid']) && in_array($state['SPMetadata']['entityid'], $this->blacklist, true)) {
                SimpleSAML_Logger::debug(
                    "[attrauthcomanage] process: Skipping blacklisted SP "
                    . var_export($state['SPMetadata']['entityid'], true));
                return;
            }
            if (empty($state['Attributes']['urn:oid:1.3.6.1.4.1.5923.1.1.1.9'])) {
                SimpleSAML_Logger::debug(
                    "[attrauthcomanage] 'eduPersonScopedAffiliation' attribute not available - skipping");
                return;
            }
            foreach ($state['Attributes']['urn:oid:1.3.6.1.4.1.5923.1.1.1.9'] as $epsa) {
                if (strpos($epsa, "@") === false) {
                    continue;    
                }
                $epsaArray = preg_split("~@~", $epsa, 2);
                $foundAffiliation = $epsaArray[0];
                $state['Attributes']['urn:oid:1.3.6.1.4.1.25178.1.2.9'] = array($epsaArray[1]);
                if (in_array($foundAffiliation, $this->memberAffiliations)) {
                    $state['Attributes']['urn:oid:1.3.6.1.4.1.5923.1.1.1.5'] = array('member');
                    break;
                } else {
                    $state['Attributes']['urn:oid:1.3.6.1.4.1.5923.1.1.1.5'] = array($foundAffiliation);
                }
            }
            SimpleSAML_Logger::debug("[attrauthcomanage] updated attributes="
                . var_export($state['Attributes'], true));
        } catch (\Exception $e) {
            $this->showException($e);
        }
    }

    private function showException($e)
    {
        $globalConfig = SimpleSAML_Configuration::getInstance();
        $t = new SimpleSAML_XHTML_Template($globalConfig, 'affiliation:exception.tpl.php');
        $t->data['e'] = $e->getMessage();
        $t->show();
        exit();
    }
}
