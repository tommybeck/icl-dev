# Convention — portée annuelle des saisons et restrictions Vik

Version 1, 12 septembre 2026. Réponse à une question de fond posée pendant la revue tarifs : comment éviter de retomber, chaque année, sur le défaut de calcul de Vik au-delà d'une portée d'environ un an.

---

## La règle

**Une saison ou une restriction ne couvre jamais plus d'un an civil.** Un besoin qui dure plusieurs années se traduit par plusieurs lignes, une par année, jamais par une ligne aux bornes étendues. C'est déjà ce qui a été fait, sans être écrit nulle part, pour le supplément weekend et pour Pâques, l'Ascension, la Pentecôte, la Fête-Dieu et les vacances d'hiver — chacune existe en paire « 2026 » / « 2027 ». L'anomalie G, trouvée le 12 septembre sur la restriction id 2, est ce qui arrive quand la règle n'est pas suivie : une ligne de 21 mois, invisible dans les anomalies de `constat-phase-0.md` parce que personne ne l'avait relue sous cet angle.

Une exception, déjà gérée par le code : une saison qui enjambe le Nouvel An (`from > to`, piège 3 du constat) reste une seule ligne, parce que c'est une portée de quelques semaines à cheval sur deux années civiles, pas une portée pluriannuelle. La règle porte sur la durée réelle couverte, pas sur le nombre d'années civiles touchées.

**Nommage obligatoire : l'année dans le nom.** `Fall vacation 2026`, pas `Fall vacation`. C'est ce qui rend une violation de la règle repérable à l'œil, dans la liste des saisons, sans avoir à relire les dates en base.

---

## Ce que ça change concrètement, une fois par an

Chaque année, avant que les réservations de l'année suivante ne commencent à arriver en nombre — et elles arrivent tôt, jusqu'à 15 mois d'avance d'après la grille actuelle — il faut dupliquer toutes les lignes de l'année qui s'achève vers l'année suivante : mêmes chambres, même montant, mêmes jours de semaine visés, bornes de dates décalées d'un an.

**Le point de départ n'est pas la mémoire de qui a fait quoi l'an dernier**, c'est une liste tenue à jour. Deux options, pas exclusives :

1. **Une requête de constat**, à faire tourner chaque année : lister toutes les saisons et restrictions dont le nom porte une année, identifier celles dont l'année courante existe mais pas la suivante. C'est mécanique, ça ne demande aucune mémoire humaine, et ça se fait par Code via SSH comme le reste du constat.
2. **Une table Airtable des familles de tarifs récurrents** — une ligne par famille (« Weekend surcharge (rooms) », « Fall vacation (rooms) », « L'Entracte All-inclusive - no check-in Fri/Sat »), portant les chambres visées, le montant, le type, les jours de semaine, et une colonne « dernière année créée ». Plus lourd à mettre en place, mais lisible sans toucher à la base, et c'est la brique qui manque pour qu'une tâche planifiée puisse un jour proposer elle-même la liste plutôt que de la calculer par requête à chaque fois.

Recommandation : commencer par l'option 1, qui ne demande rien de nouveau à construire. Construire l'option 2 seulement si la liste devient assez longue pour que la relire chaque année devienne pénible — elle ne l'est pas encore, à sept familles.

**Un rendez-vous annuel, pas une surveillance continue.** Ce n'est pas un cas pour la surveillance quotidienne de la phase 5 : c'est un geste de configuration, une fois par an. Le bon véhicule est une tâche planifiée annuelle, comme celles déjà en place pour le rythme marketing (`ilc-marketing` §10) : autour de septembre, avant l'entrée dans la fenêtre de réservation de l'année N+2. Elle produit la liste des familles à dupliquer, pas les lignes elles-mêmes — la création reste un geste explicite de Thomas, ou un prompt Code écrit à partir de cette liste, jamais une écriture automatique sans validation.

**Vérification après coup, systématique.** Chaque duplication se referme comme le prompt A6 : une requête qui confirme que la nouvelle ligne rejoint bien les bonnes chambres, et une mise à jour du tableau de référence de l'oracle tarifaire (phase 5), qui doit connaître le nouveau tarif dès sa création plutôt que d'attendre de le détecter en écart.

---

---

## Décision du 12 septembre 2026 : pas d'outil maison, les écrans de Vik restent le chemin d'écriture

Deux voies d'écriture ont été envisagées et écartées le même jour.

**Une écriture SQL directe par Code**, d'abord. Écartée parce que le compte MySQL est en lecture seule et que Thomas veut qu'il le reste. Ce n'est pas une limitation subie, c'est une garantie : aucun agent, aucun scénario Make, aucun script ne peut abîmer la base de réservation, quelle que soit l'erreur commise.

**Un plugin maison pour gérer saisons et restrictions**, ensuite. Il aurait apporté une déclaration versionnée, une validation avant écriture reprenant la liste exacte des anomalies trouvées en Q6, un aperçu des écarts, et la duplication annuelle. Écarté malgré cela, pour une raison plus forte que ses avantages : les écrans natifs de Vik existent et fonctionnent. Ils sont mal faits et pénibles à utiliser, mais ils passent par le chemin de sauvegarde du moteur, avec tous ses effets de bord — à commencer par la poussée des tarifs vers le channel manager Airbnb et Booking.com. Toute écriture parallèle, SQL ou plugin, aurait risqué de créer un écart silencieux entre les tarifs directs et les tarifs OTA, sur les quelque 650 réservations OTA annuelles. Construire l'outil aurait donc d'abord demandé d'établir comment Vik propage une modification de saison, puis de reproduire cette propagation. Coût réel élevé, pour remplacer une pénibilité d'usage par un risque de divergence.

**Ce qui reste, en conséquence.** La chaîne est toujours la même, et elle ne change plus : Cowork formule la correction dans une revue, Thomas la saisit dans Vik, Code la vérifie par requête et met à jour le constat. La validation que le plugin aurait faite avant écriture se fait après, par vérification, et la surveillance quotidienne de la phase 5 attrape ce qui passerait entre les mailles.

Cette décision se relit si un jour le volume de saisie devient déraisonnable, ou si la propagation vers le channel manager s'avère inexistante, auquel cas l'argument principal tombe.

---

## Prochaine étape suggérée

Une fois le lot du 12 septembre passé (Fall vacation, restriction id 2), écrire la requête de constat de l'option 1 et la garder dans `constat-phase-0.md` ou dans un nouveau fichier `constat-saisons-annuelles.md`, pour qu'elle serve de base au prochain rendez-vous de septembre.

Le skill évoqué pour « adapter la tarification » garde tout son sens maintenant que l'écriture reste manuelle, mais il change de nature : il ne produit pas des lignes en base, il produit **la fiche de saisie** que Thomas suit à l'écran, comme le fait le prompt A6. C'est là que se loge la valeur, parce que c'est la traduction entre le modèle stocké et ce que l'écran attend qui est pénible et fautive — les dates en secondes depuis le 1er janvier, les deux formats de jours de semaine, les deux délimiteurs de `idrooms`. Ce skill se construit une fois la requête de constat écrite, pas avant.
