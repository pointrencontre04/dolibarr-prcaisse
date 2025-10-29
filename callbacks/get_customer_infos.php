<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
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

// Retrieve Facture object to handle
$invoice_id = GETPOST('invoice_id', 'int');
$invoice = new Facture($db);
$res = $invoice->fetch($invoice_id);
if ($res <= 0) {
    http_response_code(404);
    exit('Facture invalide.');
}

$thirdparty_id = $invoice->socid;

if (empty($thirdparty_id) || $thirdparty_id <= 0) {
    http_response_code(400);
    exit('Paramètre manquant ou invalide.');
}

$pos_source = (int) $_SESSION['takeposterminal'];

// Exclude any Generic user from this script
$numberofterminals = getDolGlobalString('TAKEPOS_NUM_TERMINALS', '1');
for ($terminal = 1; $terminal <= $numberofterminals; $terminal++) {
    $is_thirdparty_generic = ($thirdparty_id == getDolGlobalInt('CASHDESK_ID_THIRDPARTY'.$terminal));
    if ($is_thirdparty_generic) {
        exit;
    }
}

// Récupération du tiers
$thirdparty = new Societe($db);
$res = $thirdparty->fetch($thirdparty_id);
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
        $bonsalim_count = count($bonalim_list);
        $bonsalim_total = 0;
        foreach ($bonalim_list as $b) {
            $bonsalim_total += $b->amount_left;
        }
        if ($bonsalim_count > 0) {
            $bonsalim_mean = (int) ($bonsalim_total / $bonsalim_count);
        } else {
            $bonsalim_mean = 0;
        }
    } else {
        $bonsalim_total = 0;
        $bonsalim_count = 0;
        $bonsalim_mean = 0;
    }
}

// Récupération des dates de fin Adhésion
if (isset($thirdparty->array_options) && isset($thirdparty->array_options['options_adhesion_fin'])) {
    $date_adhesion_fin = $thirdparty->array_options['options_adhesion_fin'];
} else {
    $date_adhesion_fin = null;
}
if ($date_adhesion_fin && time() > $date_adhesion_fin) {
    $date_adhesion_classes = 'status_expired';
} elseif ($date_adhesion_fin && time() <= $date_adhesion_fin) {
    $date_adhesion_classes = 'status_valid';
} else {
    $date_adhesion_classes = '';
}

// Récupération des dates de fin Épicerie
if (isset($thirdparty->array_options) && isset($thirdparty->array_options['options_epicerie_fin'])) {
    $date_epicerie_fin = $thirdparty->array_options['options_epicerie_fin'];
} else {
    $date_epicerie_fin = null;
}
if ($date_epicerie_fin && time() > $date_epicerie_fin) {
    $date_epicerie_classes = 'status_expired';
} elseif ($date_epicerie_fin && time() <= $date_epicerie_fin) {
    $date_epicerie_classes = 'status_valid';
} else {
    $date_epicerie_classes = '';
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
    $sql_history = "SELECT rowid FROM ".MAIN_DB_PREFIX."facture";
    $sql_history .= " WHERE fk_soc = ".((int) $thirdparty->id);
    $sql_history .= " AND (";
    $sql_history .= "(module_source = 'takepos' AND pos_source = '".((int) $pos_source)."') OR (module_source IS NULL)";
    $sql_history .= ")";
    $sql_history .= " ORDER BY datef DESC";
    $sql_history .= " LIMIT 10";

    $resql = $db->query($sql_history);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $invoice = new Facture($db);
            $invoice->fetch($obj->rowid);

            // Calculate the amount without discounts
            $total_ttc_gross = 0;
            foreach ($invoice->lines as $line) {
                if ($line->total_ttc > 0) {
                    $total_ttc_gross += $line->total_ttc;
                }
            }

            $invoice_history[] = [
                'facid'        => $invoice->id,
                'date'         => $invoice->date,
                'amount'       => $invoice->total_ttc,
                'amount_gross' => $total_ttc_gross,
                'status'       => $invoice->status,
                'close_code'   => $invoice->close_code,
                'encours'      => $invoice->getRemainToPay(),
            ];
        }
    }
}

// Récupération de toutes les factures impayées, à partir de la dixième facture
$invoice_history_unpaid = [];
if ($user->hasRight('facture', 'read')) {
    $sql_unpaid = "SELECT unpaid.rowid FROM ".MAIN_DB_PREFIX."facture unpaid";
    $sql_unpaid .= " LEFT JOIN (" . $sql_history . ") AS recent ON unpaid.rowid = recent.rowid";
    $sql_unpaid .= " WHERE recent.rowid IS NULL";
    $sql_unpaid .= " AND unpaid.fk_soc = ".((int) $thirdparty->id);
    $sql_unpaid .= " AND unpaid.paye <> 1";
    $sql_unpaid .= " AND unpaid.fk_statut <> ".((int) Facture::STATUS_DRAFT);
    $sql_unpaid .= " AND unpaid.fk_statut <> ".((int) Facture::STATUS_CLOSED);
    $sql_unpaid .= " AND unpaid.fk_statut <> ".((int) Facture::STATUS_ABANDONED);
    $sql_unpaid .= " ORDER BY unpaid.datef DESC";

    $resql = $db->query($sql_unpaid);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $invoice = new Facture($db);
            $invoice->fetch($obj->rowid);

            // Calculate the amount without discounts
            $total_ttc_gross = 0;
            foreach ($invoice->lines as $line) {
                if ($line->total_ttc > 0) {
                    $total_ttc_gross += $line->total_ttc;
                }
            }

            $invoice_history_unpaid[] = [
                'facid'        => $invoice->id,
                'date'         => $invoice->date,
                'amount'       => $invoice->total_ttc,
                'amount_gross' => $total_ttc_gross,
                'status'       => $invoice->status,
                'close_code'   => $invoice->close_code,
                'encours'      => $invoice->getRemainToPay(),
            ];
        }
    }
}


?>
<div class="customer_infos_content">
    <div class="customer_infos_general">
        <div class="date_adhesion <?php echo $date_adhesion_classes; ?>">
            <div class="label">Date de fin adhésion&nbsp;:</div>
            <?php if ($date_adhesion_fin): ?>
                <div class="value"><?php echo date('d M Y', $date_adhesion_fin); ?></div>
            <?php else: ?>
                <div class="value">&empty;</div>
            <?php endif; ?>
        </div>
        <div class="date_epicerie <?php echo $date_epicerie_classes; ?>">
            <div class="label">Date de fin épicerie&nbsp;:</div>
            <?php if ($date_epicerie_fin): ?>
                <div class="value"><?php echo date('d M Y', $date_epicerie_fin); ?></div>
            <?php else: ?>
                <div class="value">&empty;</div>
            <?php endif; ?>
        </div>
        <div class="famille">
            <div class="label">Membres du foyer&nbsp;:</div>
            <div class="value"><?php echo $nombrecontacts; ?></div>
        </div>
        <div class="encours <?php echo $encours_classes; ?>">
            <div class="label">Montant encours&nbsp;:</div>
            <div class="value"><?php echo price($encours['opened'], 0, $langs, 0, 0, -1, $conf->currency); ?></div>
        </div>
        <?php if (isModEnabled('prbonalim') && $user->hasRight('bonalim@prbonalim', 'read')): ?>
        <div class="bonalim">
            <div class="label">Bons disponibles&nbsp;:</div>
            <div class="value"><?php echo price($bonsalim_total, 0, $langs, 0, 0, -1, $conf->currency); ?> (<?php echo $bonsalim_count ?> &#x00D7; <?php echo $bonsalim_mean ?>)</div>
        </div>
        <?php endif; ?>
        <div class="credit <?php echo $credit_classes; ?>">
            <div class="label">Avance disponible&nbsp;:</div>
            <div class="value"><?php echo price($credit_total, 0, $langs, 0, 0, -1, $conf->currency); ?></div>
        </div>
    </div>
    <div class="customer_infos_notes">
        <?php if ($note_public): ?>
        <div class="note_public">
            <div class="label">Note publique&nbsp;:</div>
            <div class="value"><?php echo dol_escape_htmltag($note_public); ?></div>
        </div>
        <?php endif; ?>
        <?php if ($note_private): ?>
        <div class="note_private">
            <div class="label">Note Privée&nbsp;:</div>
            <div class="value"><?php echo dol_escape_htmltag($note_private); ?></div>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($invoice_history): ?>
    <div class="invoice_history invoice_history_last">
        <div class="label">10 derniers passages sur cette caisse</div>
        <div class="value">
            <div class="invoice_history_line invoice_history_table_header">
                <div>Date</div>
                <div>Montant</div>
                <div>À régler</div>
            </div>
            <?php foreach ($invoice_history as $line): ?>
                <div class="invoice_history_line <?php if ($invoice_id && $invoice_id == $line['facid']) { echo 'active'; } ?>" onclick="$('#poslines').load('invoice.php?action=history&placeid=<?php echo (int) $line['facid']; ?>', function() {place='0'})">
                    <div class="date"><?php echo dol_print_date($line['date']) ?></div>
                    <div class="amount"><?php echo price($line['amount_gross'], 0, $langs, 0, 0, -1, $conf->currency, 0, $langs, 0, 0, -1, $conf->currency) ?></div>
                    <?php if ($line['status'] == Facture::STATUS_CLOSED && $line['close_code'] == 0 && $line['encours'] == 0): ?>
                        <div class="encours paid">Payé</div>
                    <?php elseif ($line['status'] == Facture::STATUS_ABANDONED): ?>
                        <div class="encours closed">Fermé</div>
                    <?php else: ?>
                        <div class="encours unpaid"><?php echo price($line['encours'], 0, $langs, 0, 0, -1, $conf->currency, 0, $langs, 0, 0, -1, $conf->currency) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($invoice_history_unpaid): ?>
    <div class="invoice_history invoice_history_unpaid">
        <div class="label">Autres factures impayées</div>
        <div class="value">
            <div class="invoice_history_line invoice_history_table_header">
                <div>Date</div>
                <div>Montant</div>
                <div>À régler</div>
            </div>
            <?php foreach ($invoice_history_unpaid as $line): ?>
                <div class="invoice_history_line <?php if ($invoice_id && $invoice_id == $line['facid']) { echo 'active'; } ?>" onclick="$('#poslines').load('invoice.php?action=history&placeid=<?php echo (int) $line['facid']; ?>', function() {place='0'})">
                    <div class="date"><?php echo dol_print_date($line['date']) ?></div>
                    <div class="amount"><?php echo price($line['amount_gross'], 0, $langs, 0, 0, -1, $conf->currency, 0, $langs, 0, 0, -1, $conf->currency) ?></div>
                    <?php if ($line['status'] == Facture::STATUS_CLOSED && $line['close_code'] == 0 && $line['encours'] == 0): ?>
                        <div class="encours paid">Payé</div>
                    <?php elseif ($line['status'] == Facture::STATUS_ABANDONED): ?>
                        <div class="encours closed">Fermé</div>
                    <?php else: ?>
                        <div class="encours unpaid"><?php echo price($line['encours'], 0, $langs, 0, 0, -1, $conf->currency, 0, $langs, 0, 0, -1, $conf->currency) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

