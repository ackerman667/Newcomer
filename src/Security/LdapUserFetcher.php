<?php

namespace App\Security;

class LdapUserFetcher
{
    public function getUserInfoByUid(string $uid): ?array
    {
        $ds = @ldap_connect($_SERVER["ANNU_URL"], $_SERVER["ANNU_PORT"]);

        if ($_SERVER["OPENLDAP"] === "OUI") {
            ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
        }

        $r = ldap_bind($ds, $_SERVER["ANNU_LOGIN"], $_SERVER["ANNU_PASSWD"]);
        if (!$r) {
            return null;
        }

        $result = ldap_search($ds, $_SERVER["ANNU_BASE"], "uid=$uid");
        $nb = ldap_count_entries($ds, $result);

        if ($nb > 0) {
            $info = ldap_get_entries($ds, $result);

            $fiche = [
                'codecivilite' => $info[0]['codecivilite'][0] ?? '',
                'cn'           => $info[0]['cn'][0] ?? '',
                'datenaissance'=> $info[0]['datenaissance'][0] ?? '',
                'mail'         => $info[0]['mail'][0] ?? '',
                'sn'           => $info[0]['sn'][0] ?? '',
                'givenname'    => $info[0]['givenname'][0] ?? '',
                'title'        => $info[0]['title'][0] ?? '',
                'uid'          => $info[0]['uid'][0] ?? '',
            ];

            ldap_close($ds);
            return $fiche;
        }

        ldap_close($ds);
        return null;
    }
}
