<?php

/**
 * SimpleSAMLphp authproc filter for extracting the user's primary affiliation
 * and home organisation attributes from the provided scoped affiliation.
 *
 * The filter generates a primary affiliation (eduPersonPrimaryAffiliation)
 * value based on the affiliation information contained in the scoped
 * affiliation attribute (e.g. voPersonExternalAffiliation or 
 * eduPersonScopedAffiliation) value(s).
 * Specifically, in the presence of a single-valued scoped affiliation
 * attribute, the primary affiliation is derived from the affiliation value
 * contained in that scoped affiliation attribute. In the case of a
 * multi-valued scoped affiliation attribute, the filter assigns the "member"
 * affiliation for one or more of the following affiliations:
 *   - "faculty" or
 *   - "staff" or
 *   - "student" or
 *   - "employee"
 *
 * The module assumes that:
 *  1. the scoped affiliation attribute name is found from the ordered
 *     candidate list:
 *       i.  "urn:oid:1.3.6.1.4.1.25178.4.1.11" or
 *       ii. "urn:oid:1.3.6.1.4.1.5923.1.1.1.9"
 *  2. the eduPersonPrimaryAffiliation attribute name is expressed as:
 *     "urn:oid:1.3.6.1.4.1.5923.1.1.1.5"
 *  3. the schacHomeOrganization attribute is expressed as:
 *     "urn:oid:1.3.6.1.4.1.25178.1.2.9"
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

    private $candidates = array(
        'urn:oid:1.3.6.1.4.1.25178.4.1.11',  // voPerson v2.0
        'urn:oid:1.3.6.1.4.1.5923.1.1.1.9',  // eduPerson
    );
    
    private $memberAffiliations = array(
        'faculty',
        'staff',
        'student',
        'employee',
        'member',
    );

    private $eduPersonPrimaryAffiliation = "urn:oid:1.3.6.1.4.1.5923.1.1.1.5";

    private $schacHomeOrganization = "urn:oid:1.3.6.1.4.1.25178.1.2.9";

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
        assert('is_array($state)');

        if (isset($state['SPMetadata']['entityid']) && in_array($state['SPMetadata']['entityid'], $this->blacklist, true)) {
            SimpleSAML_Logger::debug("[PrimaryAffiliation] process: Skipping blacklisted SP " . var_export($state['SPMetadata']['entityid'], true));
            return;
        }

        foreach ($this->candidates as $scopedAffiliation) {
            if (!empty($state['Attributes'][$scopedAffiliation])) {
                $foundScopedAffiliation = $scopedAffiliation;
                break;
            }
        }

        if (empty($foundScopedAffiliation)) {
            SimpleSAML_Logger::debug("[PrimaryAffiliation] 'Scoped Affiliation' attribute not available - skipping");
            return;
        }

        foreach ($state['Attributes'][$foundScopedAffiliation] as $epsa) {
            SimpleSAML_Logger::debug("[PrimaryAffiliation] 'Scoped affiliation' value found: " . $epsa);
            if (strpos($epsa, "@") === false) {
                continue;
            }
            $epsaArray = preg_split("~@~", $epsa, 2);
            $foundAffiliation = $epsaArray[0];
            $state['Attributes'][$this->schacHomeOrganization] = array($epsaArray[1]);
            if (in_array($foundAffiliation, $this->memberAffiliations)) {
                $state['Attributes'][$this->eduPersonPrimaryAffiliation] = array('member');
                break;
            } else {
                $state['Attributes'][$this->eduPersonPrimaryAffiliation] = array($foundAffiliation);
            }
        }
        SimpleSAML_Logger::debug("[PrimaryAffiliation] updated attributes="
            . var_export($state['Attributes'], true));
    }

}
