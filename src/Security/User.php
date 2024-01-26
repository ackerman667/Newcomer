<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface
{
    private $verapp;
    private $uid;
    private $codecivilite;
    private $cn;
    private $datenaissance;
    private $mail;
    private $urlproxy;
    private $urllogout;
    private $urlportail;
    private $typeappli;
    private $domain;
    private $appli;
    private $libappli;
    private $theme;

    private $roles = [];

    //OM

    public function __construct ( $uid ,$TypeAppliDomainTheme, $utilisateur=null )
    {
        //dd($utilisateur);
        $this -> verapp = $_SERVER['VERAPP'];
        $this -> uid = $uid ;
        $this -> typeappli = $TypeAppliDomainTheme->getTadt()['type'];
        $this -> appli = $TypeAppliDomainTheme->getTadt()['appli'];
        $this -> libappli = $TypeAppliDomainTheme->getTadt()['libappli'];
        $this -> domain = $TypeAppliDomainTheme->getTadt()['domain'];
        $this -> theme = $TypeAppliDomainTheme->getTadt()['theme'];
        if (isset($utilisateur['codecivilite']))
            $this -> codecivilite = $utilisateur['codecivilite'];
        if (isset($utilisateur['cn']))
            $this -> cn = $utilisateur['cn'];
        if (isset($utilisateur['datenaissance']))
            $this -> datenaissance = $utilisateur['datenaissance'];
        if (isset($utilisateur['mail']))
            $this -> mail = $utilisateur['mail'];
        if (isset($utilisateur['urlproxy']))
            $this -> urlproxy = $utilisateur['urlproxy'];
        if (isset($utilisateur['urllogout']))
            $this -> urllogout = $utilisateur['urllogout'];
        if (isset($utilisateur['urlportail']))
            $this -> urlportail = $utilisateur['urlportail'];       
        if (isset($utilisateur['roles']))
            $this -> roles = $utilisateur['roles'] ;
    }

    public function getVerapp(): ?string
    {
        return $this->verapp;
    }

    public function setVerapp(string $verapp): self
    {
        $this->verapp = $verapp;

        return $this;
    }

    public function getUid(): ?string
    {
        return $this->uid;
    }

    public function setUid(string $uid): self
    {
        $this->uid = $uid;

        return $this;
    }

    public function getTypeappli(): ?string
    {
        return $this->typeappli;
    }

    public function setTypeappli(string $type): self
    {
        $this->typeappli = $typeappli;

        return $this;
    }
    public function getAppli(): ?string
    {
        return $this->appli;
    }

    public function setAppli(string $appli): self
    {
        $this->appli = $appli;

        return $this;
    }
    public function getLibappli(): ?string
    {
        return $this->libappli;
    }

    public function setLibappli(string $libappli): self
    {
        $this->libappli = $libappli;

        return $this;
    }
    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): self
    {
        $this->domain = $domain;

        return $this;
    }
    public function getTheme(): ?string
    {
        return $this->theme;
    }

    public function setTheme(string $Theme): self
    {
        $this->theme = $Theme;

        return $this;
    }
    public function getCodecivilite(): ?string
    {
        return $this->codecivilite;
    }

    public function setCodecivilite(string $Codecivilite): self
    {
        $this->codecivilite = $Codecivilite;

        return $this;
    }

    public function getCn(): ?string
    {
        return $this->cn;
    }

    public function setCn(string $Cn): self
    {
        $this->cn = $Cn;

        return $this;
    }

    public function getDatenaissance(): ?string
    {
        return $this->datenaissance;
    }

    public function setDatenaissance(string $Datenaissance): self
    {
        $this->datenaissance = $Datenaissance;

        return $this;
    }

    public function getMail(): ?string
    {
        return $this->mail;
    }

    public function setMail(string $Mail): self
    {
        $this->mail = $Mail;

        return $this;
    }

    public function getUrlproxy(): ?string
    {
        return $this->urlproxy;
    }

    public function setUrlproxy(string $Urlproxy): self
    {
        $this->urlproxy = $Urlproxy;

        return $this;
    }

    public function getUrllogout(): ?string
    {
        return $this->urllogout;
    }

    public function setUrllogout(string $Urllogout): self
    {
        $this->urllogout = $Urllogout;

        return $this;
    }
    public function getUrlportail(): ?string
    {
        return $this->urlportail;
    }

    public function setUrlportail(string $Urlportail): self
    {
        $this->urlportail = $Urlportail;

        return $this;
    }
    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUsername(): string
    {
        return (string) $this->uid;
    }
    public function getUserIdentifier(): string
    {
        return (string) $this->uid;
    }    

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        if ($_SERVER['ROLE_DEFAUT']=="OUI")
            $roles = array($_SERVER['ROLE_DEFAUT_NAME']);

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getPassword()
    {
        // not needed for apps that do not check user passwords
    }

    /**
     * @see UserInterface
     */
    public function getSalt()
    {
        // not needed for apps that do not check user passwords
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }
}
