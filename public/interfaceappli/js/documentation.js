$("document").ready(function (){
    // Constructeur de sommaire automatique

    var nbritem=$(".documentation h1,.documentation h2, .documentation h3").length; // Compte le nombre de titres dans le block documentation
    if(nbritem > 0){
      //console.log('oui'); // controle si il y a des titres dans le block
      
      const montableau=[]; //mon tableau de titres de différents niveaux
      const titreh3=[]; // mes H3
      const mem=[]; // Mes h3 de même niveau et sous enfants
      const serie=[]; // mes parents de h3
      $(".documentation h1,.documentation h2, .documentation h3").each(function(i) {
        $(".page-sous-menu").css({"display":"block"});
        var current = $(this);
        var target = current.prop("tagName");
        montableau[i]=target; // rempli le tableau montableau avec les tag de type titre
        
        current.attr("id", "title-" + i); // Donne à chaque titre un id pour créer les ancres
        var tagcontent = current.text(); // récupère le text du titre
        $(".page-sous-menu").append("<li><i class='fas fa-arrow-right'></i> <a id='link" + i + "' href='#title-" + i + "' title='" + tagcontent + "'>" + tagcontent + "</a></li>"); // Contruit les titres du sommaire
      });

      // construit le tableau de l'arborescence des titres
      //console.log(montableau); // mon tableau de titres
      
      if(jQuery.inArray("H3", montableau) !== -1){

        $.each( montableau, function( key, value ) {
          if (value == "H3"){ // Test si une des valeurs est H3 ... attention à la casse
            console.log( key + ": " + value); // Contrôle du tableau de titres
            if (montableau[key-1] == "H2"){ // vérifie si il y a un h2 ou un h1 avant
              console.log('est sous enfant');
              var mykey=key;
              titreh3.push(key); // j'ajoute ceux qui sont des sous titres
              serie.push(key-1); // j'ajoute le premier élément dans le tableau de parents
            }
            else if(montableau[key-1] == "H3"){
              console.log('c\'est le même niveau');
              var mykey=key;
              mem.push(mykey-1);// j'ajoute ceux qui sont plusieurs sous titres de même niveau
              mem.push(mykey);// j'ajoute ceux qui sont plusieurs sous titres de même niveau
              titreh3.push(mykey);// j'ajoute l'élément dans le tableau des enfants
            }
            else{
              //console.log('n\'est pas sous enfant'); //Contrôle si n'est pas enfant
            }
          }
          else {
            //console.log( "différent de h3" ); //Contrôle si n'est pas H3
          }
        });
        
      }
      else{
        //console.log("non, pas de h3 dans le tableau"); // controle négatif de présente de sous titres 
      }
      
      //console.log("mon tableau de h3 enfants " +titreh3); // constrôle du tableau de sous titres
      //console.log("mon tableau de h3 de même niveau " +mem); // constrôle du tableau de sous titres de même niveau
      //console.log("mon tableau de parents " +serie); // constrôle du tableau de parents
      
      $.each( serie, function(key,value){ // Cherche les parents dans le tableau "serie"
        $(".page-sous-menu li:nth-child("+(value +1)+")").addClass("parli");
      });
      $.each( titreh3, function(key,value){ // parcours le tableau de sous titres et leur affecte une class "subli"
        var monnoeud =  $(".page-sous-menu li:nth-child("+(value +1)+")");
        monnoeud.addClass("subli");
      });

      $('.subli').each(function() { // Ajoute chaque sous menu (subli) à son parent (parli)
        $(this).prev('.parli').append(this);
      });
      
        $(".parli").wrapInner("<ul></ul>"); // ajoute les ul aux parents 
        $(".page-sous-menu").wrapInner("<ul></ul>"); // ajoute un Ul au block


      // construit les liens vers ancres du sommaire  
      $(".page-sous-menu li a").click(function(event){
          event.preventDefault();
          var full_url = this.href;
          var parts = full_url.split("#");
          var trgt = parts[1];
          var target_offset = $("#"+trgt).offset();
          var target_top = target_offset.top;
          $('html, body').animate({scrollTop:target_top}, 500);
      });
      // Crée un block avec le sommaire à partir de l'arbre de titre trouvé dans .documentation
      $(".page-sous-menu").prepend("<span class='sommaire tooltipster' title='Afficher ou masquer le sommaire'>Sommaire</span>");
      
    }
    
    
    $(window).scroll(function(){ // Détecte le scroll pour fixer le block sommaire
      var scrolltop = $(document).scrollTop();
      scrolltop = scrolltop - 50;
        if(scrolltop > 500){
        $('.page-sous-menu').addClass('page-sous-menu-fixed');
        $('.page-sous-menu').children("ul").fadeOut();
        }
        if(scrolltop < 500){
        $('.page-sous-menu').removeClass('page-sous-menu-fixed');
        $('.page-sous-menu').children("ul").fadeIn();
        }
    });
    $(".page-sous-menu .sommaire").click(function(){ // bouton pour ouvrir/fermer le block sommaire
      $(".page-sous-menu").children("ul").fadeToggle();
    });
    
    $('.tooltipster').tooltipster({ // affiche l'étiquette au survol du bouton sommaire
        animation: 'grow',
        theme: 'tooltipster-shadow'
      });

});