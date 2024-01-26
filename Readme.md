# Fichier .env.local
    URLAPI="http://srvweb08.in.ac-guadeloupe.fr/api/mention"
    TOKENAPI="9999999999999999999999999999999999999999999"
    DATABASE_URL="postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=15&charset=utf8"

    HTTP_CT_WEB_SVR_ID=
    HTTP_RNE=
    HTTP_CT_REMOTE_USER=
    HTTP_CTDN=

    OPENLDAP=NON
    ANNU_URL=ldap8-m1.in.ac-guadeloupe.fr
    ANNU_PORT=389
    ANNU_LOGIN="uid=appgwada,ou=PAG,ou=education,o=gouv,c=fr"
    ANNU_PASSWD=xxxxxxxxxxxxxxxx
    ANNU_BASE=ou=ac-guadeloupe,ou=education,o=gouv,c=fr


    #Est-on sur de l'openldap OUI ou NON
    OPENLDAP=NON

# Entrée LDAP à créer
    dn: ou=newcomer,ou=ApplicationsGwada,ou=PAG,ou=education,o=gouv,c=fr
    objectClass: organizationalUnit
    objectClass: top
    ou: newcomer
    businessCategory: Application locale
    description: Nouveaux arrivants
    l: /newcomer/\*
    postalAddress: apptec
    st: Nouveaux arrivants
    street: /newcomer/public

    dn: cn=admin,ou=newcomer,ou=ApplicationsGwada,ou=PAG,ou=education,o=gouv,c=fr
    objectClass: groupOfUniqueNames
    objectClass: top
    cn: admin
    uniqueMember: cn=toto

    dn: cn=ROLE_USER,ou=newcomer,ou=ApplicationsGwada,ou=PAG,ou=education,o=gouv,c=fr
    objectClass: groupOfUniqueNames
    objectClass: top
    cn: ROLE_USER
    uniqueMember: uid=omaureaux,ou=Personnels EN,ou=ac-guadeloupe,ou=education,o=gouv,c=fr