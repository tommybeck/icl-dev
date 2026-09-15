<?php
/**
 * astra-child — habillage Sexcape Room des pages du tunnel de réservation.
 *
 * Chantier D, docs/briefs/brief-habillage-tunnel.md, et chapitre 4.1 du
 * brief docs/briefs/sexcape-room-reservation.md. Conditionné à l'hôte de la
 * marque `sexcaperoom` telle que déclarée dans
 * mu-plugins/lme-brands/config/brands.php — jamais une comparaison de
 * chaîne écrite en dur ici, jamais un identifiant d'hôte en dur. Les pages
 * servies sous l'hôte L'Instant Clé (ou tout hôte sans marque, ex.
 * staging10.linstantcle.ch) ne chargent rien de ce fichier et gardent
 * l'apparence Astra existante, inchangée.
 *
 * Aucune valeur de couleur, de police ou d'URL de logo n'est écrite en dur
 * ici : tout vient de la clé `appearance` de la marque courante, dans le
 * registre. Ce fichier ne fait que sérialiser ce tableau en CSS.
 *
 * Stratégie. Vik Booking porte déjà son propre système de jetons CSS
 * (`--vbo-*`, wp-content/plugins/vikbooking/site/resources/vikbooking_styles.css)
 * — c'est ce qui pilote fonds, bordures, textes et champs sur les sept
 * écrans du tunnel, vérifié dans le code de Vik (fond des champs sur
 * `var(--vbo-input-style)`, bordures sur `var(--vbo-border-color)`, etc.).
 * On redéclare ces jetons en `:root` plutôt que de réécrire chaque
 * sélecteur un par un : « on reproduit une identité, on ne la réinvente
 * pas » (brief-habillage-tunnel.md). `!important` sur chacun : Vik pose
 * aussi ses `--vbo-pref-*` en `<style>` inline, depuis sa configuration
 * globale, sur toutes les vues front-end
 * (VikBooking::loadPreferredColorStyles(), appelée pour toutes les marques
 * — un seul réglage Vik pour les deux hôtes). On ne touche jamais ce
 * réglage dans Vik (règle absolue n°1 : jamais les fichiers d'un plugin
 * tiers, et un changement dans Vik s'appliquerait aussi à L'Instant Clé) ;
 * on écrase seulement, sous cet hôte, le jeton CSS qui en résulte —
 * `!important` garantit que ça gagne quel que soit l'ordre d'impression
 * dans <head>, qu'on ne contrôle pas depuis un mu-plugin tiers.
 *
 * Volontairement non reteintés : --vbo-green-color, --vbo-orange-color,
 * --vbo-red-color-hover (succès, avertissement) et les --vbo-tag-*
 * (étiquettes d'administration). Aucune valeur de marque n'existe pour eux
 * dans le brief d'habillage ; les couleurs par défaut de Vik restent
 * lisibles sur fond sombre. Les rouges d'erreur (--vbo-red-color) sont
 * reteintés, eux, parce que le brief définit explicitlement `--srlm-alerte`
 * pour ce rôle.
 *
 * Ce que ce fichier ne fait pas : construire l'en-tête ou le pied de page
 * du tunnel. Ils vivent dans Ultimate Addons, condition d'affichage sur cet
 * hôte, geste de Cowork/Thomas dans Elementor (brief-habillage-tunnel.md,
 * « Qui construit les en-têtes »). Ce fichier fournit seulement les jetons
 * et la classe `.srlm-marque-nom` que ces gabarits pourront utiliser.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LME_SEXCAPEROOM_TUNNEL_DIR', get_stylesheet_directory() );
define( 'LME_SEXCAPEROOM_TUNNEL_URI', get_stylesheet_directory_uri() );

/**
 * Habillage de la marque de la requête courante, seulement si la requête
 * est sur l'hôte de cette marque et que le registre déclare une clé
 * 'appearance' complète. null sinon — y compris sur linstantcle.ch, qui n'en
 * a délibérément aucune.
 *
 * Une clé 'appearance' présente mais incomplète (colors/radii/fonts/shell
 * manquants) est une erreur de configuration, journalisée en erreur, jamais
 * un habillage partiel deviné : chapitre 6 du brief principal.
 *
 * @return array|null
 */
function lme_sexcaperoom_current_appearance() {
	if ( ! function_exists( 'lme_brands_current_request_brand_key' ) ) {
		// mu-plugin lme-brands absent : aucun habillage, jamais une
		// supposition d'hôte.
		return null;
	}

	$brand_key = lme_brands_current_request_brand_key();

	if ( null === $brand_key ) {
		return null;
	}

	$config = lme_brands_get_config();
	$brand  = isset( $config['brands'][ $brand_key ] ) ? $config['brands'][ $brand_key ] : null;

	if ( ! is_array( $brand ) || empty( $brand['appearance'] ) || ! is_array( $brand['appearance'] ) ) {
		// Cas normal pour une marque sans habillage propre (linstantcle
		// aujourd'hui) : pas une erreur.
		return null;
	}

	$appearance = $brand['appearance'];

	foreach ( array( 'colors', 'radii', 'fonts', 'shell' ) as $required_key ) {
		if ( empty( $appearance[ $required_key ] ) || ! is_array( $appearance[ $required_key ] ) ) {
			lme_brands_log(
				'error',
				'appearance_invalid',
				sprintf( "L'habillage de la marque '%s' n'a pas de clé '%s' valide : rien n'est chargé sur le tunnel.", $brand_key, $required_key ),
				array(
					'brand' => $brand_key,
					'clé'   => $required_key,
				)
			);
			return null;
		}
	}

	return $appearance;
}

add_action( 'wp_enqueue_scripts', 'lme_sexcaperoom_enqueue_tunnel_styles', 21 );

function lme_sexcaperoom_enqueue_tunnel_styles() {
	$appearance = lme_sexcaperoom_current_appearance();

	if ( null === $appearance ) {
		return;
	}

	// Jetons dynamiques (couleurs, rayons, polices, gouttières) et
	// @font-face : seul endroit qui lit des valeurs venues du registre.
	// Handle sans fichier, seulement porteur de CSS en ligne — chargé avant
	// la feuille structurelle, dont il est déclaré comme dépendance, pour
	// que les var(--srlm-*) existent avant d'être consommées.
	wp_register_style( 'lme-sexcaperoom-tokens', false, array(), null );
	wp_enqueue_style( 'lme-sexcaperoom-tokens' );
	wp_add_inline_style( 'lme-sexcaperoom-tokens', lme_sexcaperoom_build_tokens_css( $appearance ) );

	// Règles structurelles : uniquement des sélecteurs et des
	// var(--srlm-*) / var(--vbo-*), aucune valeur de marque en dur — voir
	// assets/css/sexcaperoom-tunnel.css.
	$css_path = LME_SEXCAPEROOM_TUNNEL_DIR . '/assets/css/sexcaperoom-tunnel.css';
	wp_enqueue_style(
		'lme-sexcaperoom-tunnel',
		LME_SEXCAPEROOM_TUNNEL_URI . '/assets/css/sexcaperoom-tunnel.css',
		array( 'lme-sexcaperoom-tokens' ),
		file_exists( $css_path ) ? (string) filemtime( $css_path ) : '1.0.0'
	);

	if ( ! empty( $appearance['favicon'] ) && is_string( $appearance['favicon'] ) ) {
		add_action(
			'wp_head',
			function () use ( $appearance ) {
				printf( '<link rel="icon" href="%s" />' . "\n", esc_url( $appearance['favicon'] ) );
			},
			1
		);
	}
}

/**
 * Sérialise l'habillage d'une marque en CSS : un bloc :root (jetons
 * --srlm-* propres à ce chantier, puis jetons --vbo-* de Vik Booking
 * reteintés vers eux) suivi des règles @font-face. Fonction pure — aucun
 * accès disque ni réseau, uniquement des chaînes construites depuis le
 * tableau reçu.
 *
 * @param array $appearance Voir config/brands.php, clé 'appearance'.
 * @return string
 */
function lme_sexcaperoom_build_tokens_css( array $appearance ) {
	$lines = array( ':root {' );

	foreach ( $appearance['colors'] as $key => $value ) {
		$lines[] = sprintf( '--srlm-%s: %s;', lme_sexcaperoom_css_token( $key ), $value );
	}

	foreach ( $appearance['radii'] as $key => $value ) {
		$lines[] = sprintf( '--srlm-%s: %s;', lme_sexcaperoom_css_token( $key ), $value );
	}

	foreach ( $appearance['fonts'] as $role => $font ) {
		if ( ! empty( $font['stack'] ) ) {
			$lines[] = sprintf( '--srlm-font-%s: %s;', lme_sexcaperoom_css_token( $role ), $font['stack'] );
		}
	}

	if ( ! empty( $appearance['shell']['max_width'] ) ) {
		$lines[] = sprintf( '--srlm-shell-max-width: %s;', $appearance['shell']['max_width'] );
	}

	if ( ! empty( $appearance['shell']['padding'] ) ) {
		$lines[] = sprintf( '--srlm-shell-padding: %s;', $appearance['shell']['padding'] );
	}

	foreach ( lme_sexcaperoom_vbo_token_map() as $vbo_var => $srlm_value ) {
		$lines[] = sprintf( '%s: %s !important;', $vbo_var, $srlm_value );
	}

	$lines[] = '}';

	foreach ( $appearance['fonts'] as $font ) {
		if ( empty( $font['family'] ) || empty( $font['faces'] ) || ! is_array( $font['faces'] ) ) {
			continue;
		}

		foreach ( $font['faces'] as $face ) {
			if ( empty( $face['file'] ) || ! is_string( $face['file'] ) ) {
				continue;
			}

			$url = LME_SEXCAPEROOM_TUNNEL_URI . '/assets/fonts/sexcaperoom/' . rawurlencode( $face['file'] );

			$lines[] = sprintf(
				"@font-face{font-family:'%s';src:url('%s') format('woff2');font-weight:%s;font-style:%s;font-display:swap;}",
				esc_attr( $font['family'] ),
				esc_url_raw( $url ),
				isset( $face['weight'] ) ? (int) $face['weight'] : 400,
				( isset( $face['style'] ) && 'italic' === $face['style'] ) ? 'italic' : 'normal'
			);
		}
	}

	return implode( "\n", $lines );
}

/**
 * Table de reteinte des jetons CSS propres de Vik Booking vers les jetons
 * Sexcape Room. Noms `--vbo-*` fixes (constatés dans le code de Vik, pas
 * configurables) ; valeurs en `var(--srlm-*)`, donc dérivées du registre.
 *
 * @return array<string,string>
 */
function lme_sexcaperoom_vbo_token_map() {
	return array(
		'--vbo-grey-bg-color'                  => 'var(--srlm-noir)',
		'--vbo-white-bg-color'                 => 'var(--srlm-laque)',
		'--vbo-light-grey-bg-color'            => 'var(--srlm-laque-haute)',
		'--vbo-light-dark-grey-bg-color'       => 'var(--srlm-laque-basse)',
		'--vbo-light-verydark-grey-bg-color'   => 'var(--srlm-puits)',
		'--vbo-light-dark-grey-bg-color-hover' => 'var(--srlm-laque-haute)',
		'--vbo-border-color'                   => 'var(--srlm-laiton-mat)',
		'--vbo-light-border-color'             => 'var(--srlm-laque-haute)',
		'--vbo-text-color'                     => 'var(--srlm-ivoire)',
		'--vbo-middle-text-color'              => 'var(--srlm-etain)',
		'--vbo-light-text-color'               => 'var(--srlm-etain)',
		'--vbo-body-text-color'                => 'var(--srlm-ivoire)',
		'--vbo-input-style'                    => 'var(--srlm-puits)',
		'--vbo-input-style-deactive'           => 'var(--srlm-laque-basse)',
		'--vbo-input-style-nested-deactive'    => 'var(--srlm-laque-basse)',
		'--vbo-base-color'                     => 'var(--srlm-or)',
		'--vbo-base-color-hover'               => 'var(--srlm-or-haut)',
		'--vbo-darkblue-color'                 => 'var(--srlm-or-bas)',
		'--vbo-blue-color'                     => 'var(--srlm-or)',
		'--vbo-blue-color-hover'               => 'var(--srlm-or-haut)',
		'--vbo-lightblue-color'                => 'var(--srlm-or-haut)',
		'--vbo-lightblue-color-hover'          => 'var(--srlm-or)',
		'--vbo-red-color'                      => 'var(--srlm-alerte)',
		'--vbo-red-color-hover'                => 'var(--srlm-alerte-clair)',
		// Couleurs « préférées » de la configuration globale de Vik (bouton
		// de recherche et accents assortis, VikBooking::getPreferredColors()) :
		// un seul réglage Vik pour les deux marques, qu'on ne touche pas
		// dans Vik (règle absolue n°1). On écrase seulement le jeton CSS
		// qui en résulte, sous cet hôte.
		'--vbo-pref-bgcolor'                   => 'var(--srlm-or)',
		'--vbo-pref-bgcolorhov'                => 'var(--srlm-or-haut)',
		'--vbo-pref-fontcolor'                 => 'var(--srlm-noir)',
		'--vbo-pref-fontcolorhov'               => 'var(--srlm-noir)',
		'--vbo-pref-textcolor'                 => 'var(--srlm-or)',
	);
}

/**
 * @param string $key Clé de config (ex. 'laque_haute').
 * @return string Nom de jeton CSS (ex. 'laque-haute').
 */
function lme_sexcaperoom_css_token( $key ) {
	return str_replace( '_', '-', (string) $key );
}
