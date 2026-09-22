<?php
/**
 * lme-mail-guard — bootstrap.
 *
 * Ordre de chargement : core (pur, sans WordPress) avant logger (qui en
 * dépend pour rien, mais suit la même convention que lme-brands) et avant
 * guard (qui consomme les deux).
 *
 * Ce que ce greffon fait : détourne tout e-mail sortant (wp_mail()) vers
 * une adresse fourre-tout unique, partout sauf en production stricte
 * (wp_get_environment_type() === 'production' ET hôte de la requête dans
 * la liste des hôtes de cette installation), pour recetter les parcours
 * de réservation sans jamais écrire à un vrai client.
 *
 * Ce que ce greffon NE fait JAMAIS : il ne touche ni From, ni Sender, ni
 * Reply-To d'aucun message. L'identité d'envoi par marque est posée en
 * amont par mu-plugins/lme-brands/includes/mail-brand.php, sur les
 * crochets de Vik (vikbooking_before_send_booking_mail,
 * vikbooking_before_send_mail), avant même que wp_mail() du cœur ne
 * s'exécute. La vérification n°5 de la recette de bout en bout lit les
 * en-têtes d'une confirmation pour vérifier cette identité par marque :
 * elle reste valable après ce greffon, le message arrivant simplement
 * dans la boîte fourre-tout au lieu de celle du client, avec les mêmes
 * From/Sender/Reply-To. N'ajoute jamais de réécriture d'expéditeur ici :
 * ce serait un changement de portée qui casserait cette recette.
 *
 * Réglages, posés par Thomas dans wp-config.php, jamais dans ce dépôt :
 *   - LME_MAIL_GUARD_CATCHALL_EMAIL  l'adresse fourre-tout. Non définie
 *                                    (ou vide) hors production : l'envoi
 *                                    est abandonné, jamais livré à une
 *                                    adresse par défaut (includes/guard.php).
 *   - FORCE_EMAIL_REDIRECT           échappatoire booléenne, pour le cas
 *                                    du clone qui garde le domaine de
 *                                    production (brief, chapitre 2.a) :
 *                                    à true, force le détournement même
 *                                    si les deux conditions de production
 *                                    sont réunies.
 *
 * Aucune écriture en base, aucune lecture de clé : ce greffon ne lit que
 * $_SERVER['HTTP_HOST'], wp_get_environment_type() et les deux constantes
 * ci-dessus.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LME_MAIL_GUARD_DIR', __DIR__ );

require_once LME_MAIL_GUARD_DIR . '/includes/core.php';
require_once LME_MAIL_GUARD_DIR . '/includes/logger.php';
require_once LME_MAIL_GUARD_DIR . '/includes/guard.php';
