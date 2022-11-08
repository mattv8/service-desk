<?php
/*
 * PHP Internal Functions
 */

/*  Query LDAP for list of Organizational Units
    The Org Unit array() object is built (and returned) in the following example format:
        Array (3):
        0 => Array (3)
        tree => Array (1)
            Alumni => Array (2)
            level => 2
            parent => "example"
        dn => "OU=Alumni,DC=example,DC=org"
        option => "Alumni"
*/
function get_org_units($ldap, $ldap_base) {
    
    // require_once("../conf/config.inc.php");
    // require_once("../lib/ldap.inc.php");

    if ($ldap) {
        $filter="(objectClass=organizationalunit)";
        $justthese = array("dn", "ou"); 
        $search = ldap_search($ldap, $ldap_base, $filter, $justthese);
        $errno = ldap_errno($ldap);
        // $orgUnits = ldap_get_entries($ldap, $search);
        // print_r($orgUnits);
        $ou_tree = array();

        if ( $errno ) {
            $result = "ldaperror";
            error_log("LDAP - Search error $errno  (".ldap_error($ldap).")");
        } else {

            $orgUnits = ldap_get_entries($ldap, $search);// Query LDAP for full list of organizational units

            // Build the Organizational Unit tree array object. See above for example of object format.
            $exploded_ous = array();
            for ($i=0; $i < $orgUnits['count']; $i++) {// For each Organizational Unit
                // echo $orgUnits[$i]["dn"]."<br>";
                $exploded_ous = array_reverse(ldap_explode_dn($orgUnits[$i]['dn'],2));// Explode OU's ommitting "DN=" and "OU="
                // print_r($exploded_ous);
                for ($j=2; $j < $exploded_ous['count']; $j++) {// Create sub-key for each OU in tree, ignoring top-level ($j=2)
                    $key = !isset($ou_tree[$i]['tree'][$exploded_ous[$j]]) ? $exploded_ous[$j] : $key."\0";// Handle situation in which key already exists by appending invisible character
                    $ou_tree[$i]['tree'][$key]['level'] = $j;// Capture directory tree-level
                    $ou_tree[$i]['tree'][$key]['parent'] = isset($exploded_ous[$j-1])?$exploded_ous[$j-1]:'';// Capture parent
                }
                $ou_tree[$i]['dn'] = $orgUnits[$i]['dn'];// Create key: 'dn' --Save the DN of the Organizational Unit
                $ou_tree[$i]['option'] = implode(" / ",array_keys($ou_tree[$i]['tree']));// Create key: 'tree' --Create human-readable text fields to show in drop-down
                // print_r($ou_tree[$i]);
                // echo "<br>";
            }
            
            // Sort Org Structure for better human readibility
            usort($ou_tree, 'sortByOption');
            ldap_free_result($search);

        }
    } else {
        error_log("Error: an LDAP connection was not established.");
    }
    return $ou_tree;
}

/*  Query LDAP for list of all available groups
*/
function get_groups($ldap, $ldap_group_base) {

    // require_once("../conf/config.inc.php");
    // require_once("../lib/ldap.inc.php");
    $groups = array();

    if ($ldap) {

        /* Query #3: Get Groups Units */

        $filter="(objectClass=group)";
        $justthese = array("dn"); 
        $search = ldap_search($ldap, $ldap_group_base, $filter, $justthese);
        $errno = ldap_errno($ldap);
        // $orgUnits = ldap_get_entries($ldap, $search);
        // print_r($orgUnits);
        
        if ( $errno ) {
            $result = "ldaperror";
            error_log("LDAP - Search error $errno  (".ldap_error($ldap).")");
        } else {

            $groups = ldap_get_entries($ldap, $search);// Query LDAP for full list of groups

            for ($i=0; $i < $groups['count']; $i++) {// For each group
                // echo $groups[$i]["dn"]."<br>";
                $groups[$i]['option'] = ldap_explode_dn($groups[$i]['dn'],2)[0];
                // print_r($exploded_groups);
                // echo "<br>";
            }
            usort($groups, 'sortByOption');
            
        }
        ldap_free_result($search);

    } else {
        error_log("Error: an LDAP connection was not established.");
    }
    return $groups;
}

/*
    USORT() function to sort an array by subarray value
    https://stackoverflow.com/questions/2477496/php-sort-array-by-subarray-value
*/
function sortByOption($a, $b) {
    return strcmp($a['option'], $b['option']);
}


/*
    Unicode password encoding function
    TODO: change syntax to not use curly brackets
*/
function encodePassword($password) {
    $password="\"".$password."\"";
    $encoded="";
    for ($i=0; $i <strlen($password); $i++){ $encoded.="{$password{$i}}\000";}
    return $encoded;
}

?>