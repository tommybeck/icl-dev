<?php
/**
 * astra-child — CE FICHIER N'EST PAS À DÉPLOYER TEL QUEL. NE JAMAIS ÉCRASER
 * wp-content/themes/astra-child/functions.php AVEC CETTE COPIE.
 *
 * Constaté par lecture directe du serveur le 20 septembre 2026
 * (docs/briefs/constat-deploiement-moteur.md, chapitre 2) : le fichier réel
 * fait 7390 octets, daté du 6 mai 2026, et porte déjà de la logique de
 * production sans rapport avec ce chantier — suivi de conversion Google
 * Analytics et Meta Pixel sur les réservations Vik Booking et sur les
 * commandes VikRestaurants, règles `noindex` par page pour Yoast et Rank
 * Math, visibilité de la barre d'administration pour les éditeurs,
 * surlignage des jours de check-in indisponibles sur le calendrier Vik,
 * balise de vérification de domaine Facebook, autorisation du robot
 * Facebook. Un ancien commentaire de cette ligne affirmait « ce fichier fait
 * 0 octet aujourd'hui » : c'était vrai avant la phase D2, ça ne l'est plus,
 * et écraser le fichier réel avec cette copie supprimerait tout ce qui
 * précède en silence, sans erreur PHP, découvert seulement quand un
 * indicateur (revenu publicitaire, référencement) décrocherait sans raison
 * apparente. Aucun de ces deux fichiers ne s'écrase : ils se fusionnent, un
 * geste de Thomas.
 *
 * Une seule ligne manque au fichier réel, et c'est la seule à y ajouter,
 * n'importe où au niveau racine du fichier (elle ne dépend de rien d'autre
 * qui s'y trouve, et rien d'autre n'en dépend) :
 *
 *     require_once get_stylesheet_directory() . '/inc/sexcaperoom-tunnel.php';
 *
 * Pas d'enqueue de style.css à ajouter : le fichier réel enqueue déjà
 * `style.css` sous le handle `astra-child-theme-css`, priorité 15,
 * dépendance `astra-theme-css` — en ajouter un second sous un autre handle
 * ne casserait rien mais chargerait le même fichier deux fois pour rien.
 *
 * `inc/sexcaperoom-tunnel.php` (chapitre 4.1 du brief principal, chantier D)
 * est un fichier autonome et neuf sur le serveur : aucune collision de nom de
 * fonction ou de handle avec ce qui existe déjà n'a été trouvée à la lecture
 * du fichier réel. Le retirer, ou retirer la seule ligne `require_once`
 * ajoutée ci-dessus, restaure l'apparence Astra sur les deux hôtes sans
 * toucher au reste de functions.php.
 */
