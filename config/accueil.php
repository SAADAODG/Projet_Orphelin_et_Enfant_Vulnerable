<?php

/*
|--------------------------------------------------------------------------
| Diaporama de la page d'accueil
|--------------------------------------------------------------------------
| Chaque diapositive est affichée dans l'ordre de la liste.
|
| image    : chemin de l'image dans /public
| cadrage  : 'plein'  = l'image remplit tout l'écran (les bords peuvent être coupés)
|            'gauche' = l'image est collée à gauche, le reste de l'écran est rempli par « fond »
|            'droite' = l'image est collée à droite, le reste de l'écran est rempli par « fond »
|            'haut'   = l'image est centrée en haut, sur « hauteur » de l'écran
| hauteur  : hauteur de l'image pour le cadrage 'haut' (ex : '70%')
| fond_visible : pour 'gauche' / 'droite', part de la bande de fond à garder
|            (1 = bande complète, 0.5 = moitié : l'image s'élargit et son bas est rogné)
| decalage : déplace l'image, en % de sa taille : ['x' => '-6%' (gauche), 'y' => '-5%' (haut)]
|            (penser à ajuster la limite des couleurs du « fond » si on déplace verticalement)
| position : partie de l'image à garder visible quand elle est coupée (ex : 'center top')
| fond     : couleur ou dégradé CSS derrière l'image
| slogan   : null pour ne rien afficher, sinon :
|              afficher   : true / false
|              titre      : première ligne
|              accent     : deuxième ligne (mise en valeur)
|              texte      : phrase d'accroche (petite)
|              horizontal : 'gauche' | 'centre' | 'droite'
|              vertical   : 'haut' | 'milieu' | 'bas'
|              couleur    : couleur unique de tout le texte :
|                           'blanc' | 'jaune' | 'vert' | 'rouge'
|                           (ou 'clair' = blanc + jaune, 'sombre' = vert + rouge)
|              style      : 'affiche' = grand titre, accent manuscrit souligné de rouge
|              icones     : liste de pastilles [icone (Font Awesome), libelle, couleur]
|              ornement   : true pour afficher le trait rouge ★ vert sous le slogan
|              animation  : 'glisse' = chaque ligne entre en glissant depuis la gauche
|              taille_texte : agrandit la petite phrase (1 = normal, 1.2 = 20 % plus grande)
|              couleur_texte : couleur de la petite phrase seulement ('blanc' | 'jaune' | 'vert' | 'rouge')
*/

return [
    'slides' => [
        [
            'image' => 'assets/imagesDash/f5-sans-slogan.jpg',
            'alt' => 'Enfants du Burkina Faso sur la carte du pays aux couleurs du drapeau',
            'cadrage' => 'droite',
            'position' => 'center top',
            'decalage' => ['x' => '-6%', 'y' => '-5%'],
            // Limite rouge / vert de l'image : 47,6 % de sa hauteur, remontée de 5 % => 42,6 %
            'fond' => 'linear-gradient(180deg, #e2363f 0%, #e2363f 42.6%, #1f9d48 42.6%, #1f9d48 100%)',
            'slogan' => [
                'afficher' => true,
                'titre' => 'Au Burkina Faso,',
                'accent' => 'chaque orphelin et enfant vulnérable est pris en charge',
                'texte' => "L'État protège, soigne et scolarise ses enfants : aucun n'est laissé pour compte.",
                'horizontal' => 'gauche',
                'vertical' => 'milieu',
                'couleur' => 'blanc',
                'couleur_texte' => 'jaune',
                'taille_texte' => 1.2,
                'animation' => 'glisse',
            ],
        ],
        [
            'image' => 'assets/imagesDash/f4-sans-slogan.jpg',
            'alt' => 'Un enfant vulnérable regarde vers un avenir meilleur',
            'cadrage' => 'droite',
            'fond_visible' => 0.5,
            'position' => 'center top',
            'fond' => 'linear-gradient(135deg, #2a1a10 0%, #5a3620 55%, #8a5a33 100%)',
            'slogan' => [
                'afficher' => true,
                'titre' => 'Chaque enfant',
                'accent' => 'a droit à un avenir',
                'texte' => 'Le Burkina Faso se tient aux côtés de ses enfants les plus vulnérables.',
                'horizontal' => 'gauche',
                'vertical' => 'milieu',
                'couleur' => 'blanc',
                'couleur_texte' => 'jaune',
                'taille_texte' => 1.2,
            ],
        ],
        [
            'image' => 'assets/imagesDash/f2-sans-texte.jpg',
            'alt' => 'Un écolier souriant devant le drapeau du Burkina Faso et son école',
            'cadrage' => 'plein',
            'position' => 'left center',
            'fond' => '#6fb3e8',
            'slogan' => [
                'afficher' => true,
                'style' => 'affiche',
                'titre' => 'Le Burkina Faso',
                'accent' => 'soutient ses enfants',
                'texte' => 'Parce que chaque enfant a droit à un avenir meilleur.',
                'horizontal' => 'droite',
                'vertical' => 'milieu',
                'couleur' => 'vert',
                'icones' => [
                    ['icone' => 'fa-solid fa-graduation-cap', 'libelle' => 'Éducation', 'couleur' => '#127a44'],
                    ['icone' => 'fa-solid fa-heart', 'libelle' => 'Protection', 'couleur' => '#ec3348'],
                    ['icone' => 'fa-solid fa-seedling', 'libelle' => 'Épanouissement', 'couleur' => '#f4a91c'],
                ],
                'ornement' => true,
            ],
        ],
    ],
];
