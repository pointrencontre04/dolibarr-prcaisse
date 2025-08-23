<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

$langs->load("admin");
$langs->load("companies");

// Sécurité : l'utilisateur doit être connecté
if (empty($user->id)) {
    http_response_code(403);
    exit('Accès refusé : utilisateur non connecté.');
}

if (!$user->hasRight('takepos', 'run')) {
	accessforbidden('Access to TakePOS forbidden');
}

if (!$user->rights->facture->lire) {
    accessforbidden('Access to Facture forbidden');
}

/*
// Vérifier qu’un client est sélectionné
if (empty($_SESSION['takepos_customer_id'])) {
    http_response_code(400);
    exit('Client non sélectionné');
}
*/

$id = GETPOST('id', 'int');
if (empty($id) || $id <= 0) {
    http_response_code(400);
    exit('Paramètre manquant ou invalide.');
}

// Récupération du tiers
$thirdparty = new Societe($db);
$res = $thirdparty->fetch($id);
if ($res <= 0) {
    http_response_code(404);
    exit('Client introuvable.');
}

// Autorisation : l'utilisateur doit être admin ou avoir droit de voir ce tiers
$authorized = false;

if ($user->admin) {
    $authorized = true;
} elseif ($user->rights->societe->lire) {
    // Gestion commerciale activée ?
    if (!empty($conf->global->SOCIETE_USE_COMMERCIAL_CONTACTS)) {
        // L'utilisateur est-il commercial de ce tiers ?
        if ($thirdparty->isACompanyOfUser($user->id)) {
            $authorized = true;
        }
    } else {
        // Pas de restriction commerciale → accès global autorisé
        $authorized = true;
    }
}

if (!$authorized) {
    accessforbidden('You are not permitted to load user details');
}

// Récupération de l'encours
$encours = $thirdparty->getOutstandingBills();

// Calcul du nombre de personnes dans la famille
$nombrecontacts = 1;
$sql = "SELECT COUNT(rowid) FROM ".MAIN_DB_PREFIX."socpeople WHERE fk_soc = ".((int) $thirdparty->id);
$resql = $db->query($sql);
if ($resql) {
    $obj = $db->fetch_array($resql);
    $nombrecontacts = $obj[0];
}
if (!$nombrecontacts) {
    $nombrecontacts = 1;
}

?>
<div class="customer_infos_content">
    <div class="famille"><span class="label">Membres du foyer&nbsp;:</span><span class="value"><?php echo $nombrecontacts; ?>
    <div class="encours"><span class="label">Montant encours&nbsp;:</span><span class="value"><?php echo price($encours['total_ttc'], 0, $langs, 0, 0, -1, $conf->currency); ?>
    <div class="bonalim"><span class="label">Bons alimentaires disponibles&nbsp;:</span><span class="value"><?php echo price($bonsalim_total); ?></div>
    <div class="bonalim"><span class="label">Avance disponible&nbsp;:</span><span class="value"><?php echo price($credit_total); ?></div>
</div>

