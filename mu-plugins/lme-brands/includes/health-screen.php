<?php
/**
 * lme-brands — écran de santé. Chapitre 4.2 du brief : « ajouter une
 * expérience sans mettre le registre à jour devient visible en une journée ».
 *
 * Compare le registre à sir_vikbooking_rooms et signale toute chambre
 * présente d'un côté et absente de l'autre.
 *
 * Purement visuel : cet écran ne journalise rien lui-même. La journalisation
 * en erreur (chapitre 6) a lieu au moment d'une résolution réelle — une
 * réservation, un e-mail — pas à chaque visite de cette page, pour ne pas
 * transformer un outil de diagnostic en générateur d'alertes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'lme_brands_register_health_screen' );

function lme_brands_register_health_screen() {
	add_management_page(
		'Santé lme-brands',
		'Santé lme-brands',
		'manage_options',
		'lme-brands-health',
		'lme_brands_render_health_screen'
	);
}

function lme_brands_render_health_screen() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	global $wpdb;

	$config       = lme_brands_get_config();
	$known_ids    = array_keys( $config['rooms'] );
	$excluded_ids = isset( $config['excluded_room_ids'] ) ? $config['excluded_room_ids'] : array();
	$registry_ids = array_unique( array_merge( $known_ids, $excluded_ids ) );

	echo '<div class="wrap"><h1>Santé lme-brands</h1>';

	$table        = $wpdb->prefix . 'vikbooking_rooms';
	$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;

	if ( ! $table_exists ) {
		echo '<div class="notice notice-error"><p>La table <code>' . esc_html( $table ) . "</code> est introuvable : Vik Booking n'est peut-être pas installé sur ce site.</p></div></div>";
		return;
	}

	$rows      = $wpdb->get_results( "SELECT id, name FROM {$table} ORDER BY id ASC" ); // Nom de table issu de $wpdb->prefix, aucune entrée utilisateur.
	$vik_rooms = array();
	foreach ( $rows as $row ) {
		$vik_rooms[ (int) $row->id ] = $row->name;
	}
	$vik_ids = array_keys( $vik_rooms );

	$missing_from_registry = array_values( array_diff( $vik_ids, $registry_ids ) );
	$missing_from_vik       = array_values( array_diff( $known_ids, $vik_ids ) );
	$synced                 = array_values( array_intersect( $known_ids, $vik_ids ) );

	sort( $missing_from_registry );
	sort( $missing_from_vik );
	sort( $synced );

	if ( empty( $missing_from_registry ) && empty( $missing_from_vik ) ) {
		echo '<div class="notice notice-success"><p>Le registre et Vik Booking s\'accordent sur les ' . count( $synced ) . ' chambres vendues.</p></div>';
	}

	if ( ! empty( $missing_from_registry ) ) {
		echo '<h2>Chambres présentes dans Vik, absentes du registre</h2>';
		echo '<p>À ajouter dans <code>config/brands.php</code> (clé <code>rooms</code>), ou dans <code>excluded_room_ids</code> si elles ne doivent jamais se vendre.</p>';
		lme_brands_render_health_table( $missing_from_registry, $vik_rooms, $config );
	}

	if ( ! empty( $missing_from_vik ) ) {
		echo '<h2>Chambres présentes dans le registre, absentes de Vik</h2>';
		echo '<p>Une réservation ou un e-mail pour l\'une de ces chambres échouera : Vik ne les connaît plus.</p>';
		lme_brands_render_health_table( $missing_from_vik, $vik_rooms, $config );
	}

	echo '<h2>Chambres synchronisées</h2>';
	lme_brands_render_health_table( $synced, $vik_rooms, $config );

	echo '</div>';
}

/**
 * @param int[] $room_ids
 * @param array $vik_rooms  id => name, tel que lu dans sir_vikbooking_rooms.
 * @param array $config
 */
function lme_brands_render_health_table( array $room_ids, array $vik_rooms, array $config ) {
	if ( empty( $room_ids ) ) {
		echo '<p><em>Aucune.</em></p>';
		return;
	}

	echo '<table class="widefat striped"><thead><tr><th>Chambre</th><th>Nom Vik</th><th>Marque</th><th>Expérience</th></tr></thead><tbody>';

	foreach ( $room_ids as $room_id ) {
		$resolved = lme_brands_resolve_room( $config, $room_id );

		if ( 'ok' === $resolved['status'] ) {
			$brand_label = $config['brands'][ $resolved['brand_key'] ]['label'];
			$experience  = $resolved['name'];
		} elseif ( 'excluded' === $resolved['status'] ) {
			$brand_label = '—';
			$experience  = 'chambre de test';
		} else {
			$brand_label = '—';
			$experience  = '—';
		}

		printf(
			'<tr><td>#%d</td><td>%s</td><td>%s</td><td>%s</td></tr>',
			(int) $room_id,
			esc_html( isset( $vik_rooms[ $room_id ] ) ? $vik_rooms[ $room_id ] : '—' ),
			esc_html( $brand_label ),
			esc_html( $experience )
		);
	}

	echo '</tbody></table>';
}
