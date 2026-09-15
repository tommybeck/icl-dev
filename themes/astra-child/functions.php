<?php
/**
 * astra-child — mise en file de la feuille du thème enfant.
 *
 * Fichier cible : wp-content/themes/astra-child/functions.php
 * Ce fichier fait 0 octet aujourd'hui. Colle tout ce bloc, <?php compris.
 *
 * Le handle du parent, « astra-theme-css », a été relevé dans le HTML du front
 * de sexcaperoom.ch/fr/, pas supposé. Il est déclaré en dépendance pour que
 * l'enfant sorte après le parent dans la file.
 *
 * La version est l'horodatage du fichier. À chaque enregistrement de style.css
 * l'URL change, donc le navigateur ne peut pas servir l'ancienne feuille.
 * C'est ce qui évite de confondre « le CSS ne s'applique pas » et
 * « le navigateur me montre la version d'avant ».
 */

add_action(
	'wp_enqueue_scripts',
	function () {
		$chemin = get_stylesheet_directory() . '/style.css';

		wp_enqueue_style(
			'astra-child-style',
			get_stylesheet_directory_uri() . '/style.css',
			array( 'astra-theme-css' ),
			file_exists( $chemin ) ? (string) filemtime( $chemin ) : '1.0.0'
		);
	},
	20
);

/**
 * Habillage Sexcape Room des pages du tunnel de réservation, conditionné à
 * l'hôte. Chantier D, docs/briefs/brief-habillage-tunnel.md. Fichier
 * autonome : le retirer restaure l'apparence Astra sur les deux hôtes.
 */
require_once get_stylesheet_directory() . '/inc/sexcaperoom-tunnel.php';
