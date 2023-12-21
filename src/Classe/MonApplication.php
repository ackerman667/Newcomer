<?php

/**
 * @file Application.php
 * Classe Appli permettant de récupérer toutes les informations de l'application
 * @author Olivier Mauréaux
 * @date 21/12/2023
 */
namespace App\Classe;

class MonApplication
{
    private $TypeAppli;
    private $DomaineAppli;
    private $Appli;
    private $LibAppli;
    private $VerAppli;

    public function __construct()
    {
        $this->TypeAppli=$_SERVER['TYPEAPPLI'];
        $this->DomaineAppli=$_SERVER['DOMAINEAPPLI'];
        $this->Appli=$_SERVER['APPLI'];
        $this->LibAppli=$_SERVER['LIBAPPLI'];
        $this->VerAppli=$_SERVER['VERAPP'];

    }
    public function getTypeAppli():?string
    {
        return $this->TypeAppli;
    }
    public function getDomaineAppli():?string
    {
        return $this->DomaineAppli;
    }
    public function getAppli():?string
    {
        return $this->Appli;
    }
    public function getLibAppli():?string
    {
        return $this->LibAppli;
    }
    public function getVerAppli():?string
    {
        return $this->VerAppli;
    }

}