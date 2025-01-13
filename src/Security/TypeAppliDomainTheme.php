<?php
namespace App\Security;

class TypeAppliDomainTheme
{
    private $tadt;
    public function __construct(){
        $this->tadt=$this->getAppliDomainTheme();
    }
    public function getTadt()
    {
        return $this->tadt;
    }
    private function getAppliDomainTheme() {
        //On récupère l ou de l'appli par les variables serveurs
        $Appli=strstr($_SERVER['ANNU_BASE_APPLI'],",",true);
      
        
        // APPEL LDAP
        $ds = @ldap_connect ($_SERVER["ANNU_URL"],$_SERVER["ANNU_PORT"]);
        if ($_SERVER["OPENLDAP"]=="OUI"): ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3); endif;
        $r=ldap_bind($ds,$_SERVER["ANNU_LOGIN"],$_SERVER["ANNU_PASSWD"]);
        //On va cherche le nom court de l'appli, le code domaine et type application (locale, nationale, tierce)
        $result=ldap_search($ds, $_SERVER["ANNU_BASE_APPLI"], $Appli);
        $nb=ldap_count_entries($ds,$result);
        if ($nb>0):
            $info = ldap_get_entries($ds, $result);
            $TypeAppliDomainTheme['type']=$info[0]['businesscategory'][0];
            $TypeAppliDomainTheme['appli']=$info[0]['st'][0];
            $TypeAppliDomainTheme['libappli']=$info[0]['description'][0];
            $coddomain=$info[0]['postaladdress'][0];
            // dump($info);
            // dd($AppliDomainTheme);
            //Maintenant on va chercher le libellé du code domaine et le theme de l'appli
            $result=ldap_search($ds, $_SERVER["ANNU_BASE_APPLIS_LOCALES"], "cn=".$coddomain);
            $nbgrp=ldap_count_entries($ds,$result);
            if ($nbgrp>0):
                $info = ldap_get_entries($ds, $result);
                $TypeAppliDomainTheme['domain']=$info[0]['description'][0];
                $TypeAppliDomainTheme['theme']=$info[0]['serialnumber'][0];
                return $TypeAppliDomainTheme;
            else: return null;
            endif;
        else : return null;
        endif;
        ldap_close($ds);
    }
}
