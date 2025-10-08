<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

if (isModEnabled('prbonalim')) {
    require_once DOL_DOCUMENT_ROOT . '/custom/prbonalim/class/bonalim.class.php';
}

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

// Exclude any Generic user from this script
$numberofterminals = getDolGlobalString('TAKEPOS_NUM_TERMINALS', '1');
for ($terminal = 1; $terminal <= $numberofterminals; $terminal++) {
    $is_thirdparty_generic = ($id == getDolGlobalInt('CASHDESK_ID_THIRDPARTY'.$terminal));
    if ($is_thirdparty_generic) {
        exit;
    }
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

// Récupération de l'encours
$encours = $thirdparty->getOutstandingBills();

if ($encours['opened'] == 0) {
    $encours_classes = 'status_ok';
}
if ($encours['opened'] > 0 && $encours['opened'] < 50) {
    $encours_classes = 'status_warn';
}
if ($encours['opened'] >= 50) {
    $encours_classes = 'status_crit';
}

// Récupération des bons alimentaires disponibles
if (isModEnabled('prbonalim') && $user->hasRight('bonalim@prbonalim', 'read')) {
    $bonalim = new BonAlim($db);
    $bonalim_list = $bonalim->fetchAll(
        '',
        '',
        0,
        0,
        '(beneficiary:=:' . $thirdparty->id . ') AND (status:=:' . BonAlim::STATUS_CREDITED . ')'
    );
    if ($bonalim_list) {
        $bonsalim_total = 0;
        foreach ($bonalim_list as $b) {
            $bonsalim_total += $b->amount_left;
        }
    } else {
        $bonsalim_total = 0;
    }
}

// Récupération des remises et avances
$credit_total = $thirdparty->getAvailableDiscounts();
if ($credit_total > 0) {
    $credit_classes = 'status_ok';
}

// Récupération des notes publiques et privées
$note_public = $thirdparty->note_public;
$note_private = $thirdparty->note_private;

// Récupération des dix dernières factures
$invoice_history = [];
if ($user->hasRight('facture', 'read')) {
    $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."facture";
    $sql .= " WHERE fk_soc = ".((int) $thirdparty->id);
    $sql .= " ORDER BY datef DESC";
    $sql .= " LIMIT 10";

    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $invoice = new Facture($db);
            $invoice->fetch($obj->rowid);
            $invoice_history[] = [
                'date'        => $invoice->date,
                'amount'      => $invoice->total_ttc,
                'status'      => $invoice->status,
                'close_code'  => $invoice->close_code,
                'encours'     => $invoice->getRemainToPay(),
            ];
        }
    }
}

?>
<div class="customer_infos_content">
    <div class="famille">
        <span class="label">Membres du foyer&nbsp;:</span>
        <span class="value"><?php echo $nombrecontacts; ?></span>
    </div>
    <div class="encours <?php echo $encours_classes; ?>">
        <span class="label">Montant encours&nbsp;:</span>
        <span class="value"><?php echo price($encours['opened'], 0, $langs, 0, 0, -1, $conf->currency); ?></span>
    </div>
    <?php if (isModEnabled('prbonalim') && $user->hasRight('bonalim@prbonalim', 'read')): ?>
    <div class="bonalim">
        <span class="label">Bons alimentaires disponibles&nbsp;:</span>
        <span class="value"><?php echo price($bonsalim_total, 0, $langs, 0, 0, -1, $conf->currency); ?></span>
    </div>
    <?php endif; ?>
    <div class="credit <?php echo $credit_classes; ?>">
        <span class="label">Avance disponible&nbsp;:</span>
        <span class="value"><?php echo price($credit_total, 0, $langs, 0, 0, -1, $conf->currency); ?></span>
    </div>
    <?php if ($note_public): ?>
    <div class="note_public">
        <span class="label">Note publique&nbsp;:</span>
        <span class="value"><?php echo dol_escape_htmltag($note_public); ?></span>
    </div>
    <?php endif; ?>
    <?php if ($note_private): ?>
    <div class="note_private">
        <span class="label">Note Privée&nbsp;:</span>
        <span class="value"><?php echo dol_escape_htmltag($note_private); ?></span>
    </div>
    <?php endif; ?>
    <?php if ($invoice_history): ?>
    <div class="invoice_history">
        <span class="label">Historique des passages</span>
        <div class="value">
            <div class="invoice_history_line invoice_history_table_header">
                <span>Date</span>
                <span>Montant</span>
                <span>À régler</span>
            </div>
            <?php foreach ($invoice_history as $line): ?>
                <div class="invoice_history_line">
                    <span class="date"><?php echo dol_print_date($line['date']) ?></span>
                    <span class="amount"><?php echo price($line['amount'], 0, $langs, 0, 0, -1, $conf->currency, 0, $langs, 0, 0, -1, $conf->currency) ?></span>
                    <?php if ($line['status'] == Facture::STATUS_CLOSED && $line['close_code'] == 0 && $line['encours'] == 0): ?>
                        <span class="encours paid">Payé</span>
                    <?php else: ?>
                        <span class="encours unpaid"><?php echo price($line['encours'], 0, $langs, 0, 0, -1, $conf->currency, 0, $langs, 0, 0, -1, $conf->currency) ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

