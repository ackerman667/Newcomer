<?php
namespace App\Security;

//use App\Security\User;

class UserInformation
{


    public function getUserInformation() {
        // APPEL LDAP
        $ds = @ldap_connect ($_SERVER["ANNU_URL"],$_SERVER["ANNU_PORT"]);
        if ($_SERVER["OPENLDAP"]=="OUI"): ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3); endif;
        $r=ldap_bind($ds,$_SERVER["ANNU_LOGIN"],$_SERVER["ANNU_PASSWD"]);
        $result=ldap_search($ds, $_SERVER["ANNU_BASE"], "uid=".$_SERVER["HTTP_CT_REMOTE_USER"]);
        dump($ds);
        $nb=ldap_count_entries($ds,$result);
        if ($nb>0):
            $fiche=array();
            
            $info = ldap_get_entries($ds, $result);
            dump($info);
            //On récupère certaine données de l'utlisateur
            $fiche['codecivilite']=$info[0]['codecivilite'][0];
            $fiche['cn']=$info[0]['cn'][0];
            $fiche['datenaissance']=$info[0]['datenaissance'][0];
            $fiche['mail']=$info[0]['mail'][0];
            $fiche['sn']=$info[0]['sn'][0];
            $fiche['givenname']=$info[0]['givenname'][0];
            $fiche['title']=$info[0]['title'][0];
           
            dump($fiche);
            //Recherche de l'URL du portail suivant le rev-proxy d'ou vient l'agent
            // $result=ldap_search($ds, $_SERVER["ANNU_BASE_DATAREPOSITORY"],"(ctscPEPName=".$_SERVER["HTTP_CT_WEB_SVR_ID"].")");
            $nb=ldap_count_entries($ds, $result);
            // if ($nb>0){
            //     $revproxy=ldap_get_entries($ds, $result);
            //     // $fiche['urlproxy']=$revproxy[0]['ctscpepurlprefix'][0];
            //     $fiche['urllogout']=$fiche['urlproxy'].$_SERVER['URL_LOG_OUT'];
            //     //On recherche le chemin de l'application portail /portail/public
            //     $result=ldap_search($ds, $_SERVER["ANNU_BASE_APPLI_PORTAIL"], "(ou=*)");
            //     $nb=ldap_count_entries($ds,$result);
            //     if ($nb>0){
            //         $Applis = ldap_get_entries($ds, $result);
            //         $fiche['urlportail']=$fiche['urlproxy'].$Applis[0]['street'][0];
            //     }
            // }
            //Ici on sait que l'agent est dans l'annuaire et on va lui mettre une rôle defaut
            $fiche["roles"]="ROLE_LDAP_USER";
            //dd($fiche);
            return $fiche;
        else : return null;
        endif;
        ldap_close($ds);
    }
}
