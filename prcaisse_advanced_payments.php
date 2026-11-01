<?php
/* Copyright (C) 2025		SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    prcaisse/prcaisse_advanced_payments.php
 * \ingroup prcaisse
 * \brief   Page with the content of the popup of advanced payments in TakePOS
 */

// The page calling this popup keeps using the current token
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/discount.class.php';
dol_include_once('/prcaisse/lib/prcaisse.lib.php');

$langs->loadLangs(array("main", "bills", "cashdesk", "banks", "prcaisse@prcaisse"));

$hookmanager->initHooks(array('PRCaisseAdvancedPaymentsButtons'));

if (!$user->hasRight('takepos', 'run')) {
	accessforbidden('Access to TakePOS forbidden');
}

if (!$user->hasRight('facture', 'lire')) {
	accessforbidden('Access to Facture forbidden');
}

$invoiceid = GETPOSTINT('invoiceid');

$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
if (!in_array($sortfield, array('p.datep', 'pf.amount'))) {
	$sortfield = 'p.datep';
}
if (!in_array(strtoupper($sortorder), array('ASC', 'DESC'))) {
	$sortorder = 'ASC';
}
$param = '&invoiceid='.((int) $invoiceid);

// There is no invoice yet on an empty ticket
$invoice = new Facture($db);
$invoice_exists = ($invoiceid > 0 && $invoice->fetch($invoiceid) > 0);

// List deposits applied to the invoice
$discount_labels = array(
	'(DEPOSIT)' => 'Deposit',
	'(CREDIT_NOTE)' => 'CreditNote',
	'(EXCESS RECEIVED)' => 'ExcessReceived',
);
$discounts = array();
if ($invoice_exists) {
	foreach ($invoice->lines as $line) {
		if ($line->fk_remise_except > 0) {
			$label = empty($discount_labels[$line->desc]) ? $line->desc : $langs->trans($discount_labels[$line->desc]);
			$discount = new DiscountAbsolute($db);
			if ($discount->fetch($line->fk_remise_except) > 0 && $discount->ref_facture_source) {
				$label .= ' '.$discount->ref_facture_source;
			}
			$discounts[] = array('label' => $label, 'amount' => abs((float) $line->total_ttc));
		}
	}
}

// List payments of the invoice, with the same links between tables as the
// cash control report but without table bank: BonAlim payments have no bank line
$payments = array();
if ($invoice_exists) {
	$sql = "SELECT p.rowid, p.datep, pf.amount, f.module_source, f.pos_source, cp.code, cp.libelle";
	$sql .= " FROM ".MAIN_DB_PREFIX."paiement_facture as pf, ".MAIN_DB_PREFIX."facture as f, ".MAIN_DB_PREFIX."paiement as p, ".MAIN_DB_PREFIX."c_paiement as cp";
	$sql .= " WHERE pf.fk_facture = f.rowid AND p.rowid = pf.fk_paiement AND cp.id = p.fk_paiement";
	$sql .= " AND f.rowid = ".((int) $invoice->id);
	$sql .= " AND p.entity = ".((int) $conf->entity);
	$sql .= $db->order($sortfield, $sortorder);
	$sql .= ", p.rowid ASC";

	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$payments[] = $obj;
		}
		$db->free($resql);
	} else {
		dol_print_error($db);
	}
}

// Check if the invoice can be converted to a deposit
$can_switch_invoice_type = ($invoice_exists && $invoice->status == Facture::STATUS_DRAFT && empty($payments) && empty($discounts));
if ($can_switch_invoice_type) {
	$numberofterminals = getDolGlobalInt('TAKEPOS_NUM_TERMINALS', 1);
	for ($terminal = 1; $terminal <= $numberofterminals; $terminal++) {
		if ($invoice->socid == getDolGlobalInt('CASHDESK_ID_THIRDPARTY'.$terminal)) {
			$can_switch_invoice_type = false;
			break;
		}
	}
}

$buttons = array();
if ($can_switch_invoice_type) {
	$buttons[] = array(
		'class' => '',
		'function' => 'parent.PRCaisseSwitchInvoiceType()',
		'span' => '',
		'text' => '<span class="fa fa-money-check-alt paddingrightonly"></span>'.$langs->trans('PRCaisseConvertToDeposit'),
	);
}

$parameters = array('buttons' => $buttons);
$reshook = $hookmanager->executeHooks('PRCaisseAdvancedPaymentsButtons', $parameters, $invoice);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
} elseif ($reshook == 0) { // add buttons
	$buttons = array_merge($buttons, $hookmanager->resArray);
} else { // replace buttons
	$buttons = $hookmanager->resArray;
}


/*
 * View
 */

$head = '';
if (getDolGlobalInt('TAKEPOS_COLOR_THEME') == 1) {
	$head .= '<link rel="stylesheet" href="'.DOL_URL_ROOT.'/takepos/css/colorful.css">';
}

top_htmlhead($head, '', 0, 0, array(), array('/takepos/css/pos.css.php'));

?>
<body>
<style type="text/css"><?php echo file_get_contents(__DIR__.'/css/advanced_payments.css'); ?></style>
<script type="text/javascript">
	function PRCaisseChangePaymentMode(paymentId) {
		console.log("Change payment mode of payment id="+paymentId);
		alert("<?php echo dol_escape_js($langs->transnoentitiesnoconv('PRCaisseChangePaymentModeComingSoon')); ?>");
	}

	function PRCaisseDeletePayment(paymentId) {
		console.log("Delete payment id="+paymentId);
		alert("<?php echo dol_escape_js($langs->transnoentitiesnoconv('PRCaisseDeletePaymentComingSoon')); ?>");
	}
</script>

<div class="prcaisse_advanced_payments">
	<?php if ($buttons) { ?>
	<h3><?php echo $langs->trans('PRCaissePaymentsActionButtons'); ?></h3>
	<?php } ?>
	<div class="actions_band"><?php
	foreach ($buttons as $button) {
		print '<button type="button" class="action'.($button['class'] ? ' '.$button['class'] : '').'" onclick="'.$button['function'].'"><span '.$button['span'].'>'.$button['text'].'</span></button>';
	}
	?></div>

	<?php if ($discounts) { ?>
	<h3><?php echo $langs->trans('PRCaisseAppliedDeposits'); ?></h3>
	<div class="div-table-responsive-no-min">
	<table class="noborder">
		<tr class="liste_titre">
			<?php
			print_liste_field_titre('PRCaisseLabel');
			print_liste_field_titre('PRCaisseAmount', '', '', '', '', '', '', '', 'right ');
			?>
		</tr>
		<?php foreach ($discounts as $discount) { ?>
		<tr class="oddeven">
			<td><?php echo dol_escape_htmltag($discount['label']); ?></td>
			<td class="right nowraponall"><?php echo price($discount['amount'], 0, $langs, 1, -1, -1, $conf->currency); ?></td>
		</tr>
		<?php } ?>
	</table>
	</div>
	<?php } ?>

	<h3><?php echo $langs->trans('PRCaissePayments'); ?></h3>
	<?php if (empty($payments)) { ?>
	<p><?php echo $langs->trans('PRCaisseNoPayment'); ?></p>
	<?php } else { ?>
	<div class="div-table-responsive-no-min">
	<table class="noborder">
		<tr class="liste_titre">
			<?php
			print_liste_field_titre('PRCaisseDate', $_SERVER["PHP_SELF"], 'p.datep', '', $param, '', $sortfield, $sortorder);
			print_liste_field_titre('PRCaisseAmount', $_SERVER["PHP_SELF"], 'pf.amount', '', $param, '', $sortfield, $sortorder, 'right ');
			print_liste_field_titre('PRCaisseTerminal');
			print_liste_field_titre('PRCaissePaymentMode');
			print_liste_field_titre('');
			?>
		</tr>
		<?php
		foreach ($payments as $payment) {
			$terminal_name = '-';
			if ($payment->module_source == 'takepos' && $payment->pos_source != '') {
				$terminal_name = getDolGlobalString('TAKEPOS_TERMINAL_NAME_'.$payment->pos_source, $langs->trans('TerminalName', $payment->pos_source));
			}

			$mode_key = 'PaymentTypeShort'.$payment->code;
			$mode_label = $langs->trans($mode_key);
			if ($mode_label == $mode_key) {
				$mode_label = $payment->libelle;
			}
			?>
		<tr class="oddeven">
			<td class="nowraponall"><?php echo dol_print_date($db->jdate($payment->datep), 'dayhour'); ?></td>
			<td class="right nowraponall"><?php echo price($payment->amount, 0, $langs, 1, -1, -1, $conf->currency); ?></td>
			<td><?php echo dol_escape_htmltag($terminal_name); ?></td>
			<td><?php echo dol_escape_htmltag($mode_label); ?></td>
			<td class="nowraponall">
				<?php if (prcaisseIsPaymentEditable($db, $payment)) { ?>
				<button type="button" class="action action_change_mode" onclick="PRCaisseChangePaymentMode(<?php echo (int) $payment->rowid; ?>);"><span class="fa fa-sync-alt paddingrightonly"></span><?php echo $langs->trans('PRCaisseChangePaymentMode'); ?></button>
				<button type="button" class="action action_delete" onclick="PRCaisseDeletePayment(<?php echo (int) $payment->rowid; ?>);"><span class="fa fa-trash paddingrightonly"></span><?php echo $langs->trans('PRCaisseDeletePayment'); ?></button>
				<?php } ?>
			</td>
		</tr>
			<?php
		}
		?>
	</table>
	</div>
	<?php } ?>
</div>

</body>
</html>
