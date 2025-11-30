<?php
/* Copyright (C) 2001-2005  Rodolphe Quiedeville    <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012  Regis Houssin           <regis.houssin@inodbox.com>
 * Copyright (C) 2015       Jean-François Ferry     <jfefe@aternatik.fr>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2025		SuperAdmin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       prcaisse/prcaisse_customers.php
 *	\ingroup    prcaisse
 *	\brief      Home page of prcaisse top menu
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';

if (isModEnabled('prbonalim')) {
	require_once DOL_DOCUMENT_ROOT . '/custom/prbonalim/class/bonalim.class.php';
}

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

// Load translation files required by the page
$langs_array = ["prcaisse@prcaisse"];
if (isModEnabled('prbonalim')) {
	$langs_array[] = 'prbonalim@prbonalim';
}
$langs->loadLangs($langs_array);

$action = GETPOST('action', 'aZ09');

$now = dol_now();
$max = getDolGlobalInt('MAIN_SIZE_SHORTLIST_LIMIT', 5);

// Security check - Protection if external user
$thirdparty_id = GETPOSTINT('thirdparty_id');
if (!empty($user->socid) && $user->socid > 0) {
	$action = '';
	$thirdparty_id = $user->socid;
}

// Initialize a technical object to manage hooks. Note that conf->hooks_modules contains array
//$hookmanager->initHooks(array($object->element.'index'));

// Security check (enable the most restrictive one)
//if ($user->socid > 0) accessforbidden();
//if ($user->socid > 0) $socid = $user->socid;
//if (!isModEnabled('prcaisse')) {
//	accessforbidden('Module not enabled');
//}
//if (! $user->hasRight('prcaisse', 'myobject', 'read')) {
//	accessforbidden();
//}
//restrictedArea($user, 'prcaisse', 0, 'prcaisse_myobject', 'myobject', '', 'rowid');
//if (empty($user->admin)) {
//	accessforbidden('Must be admin');
//}


/*
 * Actions
 */

// None


/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

llxHeader("", $langs->trans("CustomerSummaryPage"), '', '', 0, 0, '', '', '', 'mod-prcaisse page-index');

print load_fiche_titre($langs->trans("CustomerSummaryPage"), '', 'user');

print '<div class="fichecenter"><div class="form_wrapper">';

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="show">';

// Champ de recherche de tiers
print $form->select_company(
	$thirdparty_id,    // valeur par défaut
	'thirdparty_id',   // name du champ
	's.client=1',      // filtre SQL optionnel
	1,                 // show empty
	'',                // moreparam
	0,                 // showtype
	0,                 // forcecombo
	array(),           // exclude
	0,                 // showinactive
	'minwidth300'      // css
);

print '<input type="submit" class="button" value="' . $langs->trans("Search") . '">';

?>
<script type="text/javascript">
	jQuery(document).find('select[name="thirdparty_id"]').change(function(e) {
		console.log(e);
		jQuery(this).parent().submit();
	});
</script>
<?php

print '</form>';



/* BEGIN MODULEBUILDER DRAFT MYOBJECT
// Draft MyObject
if (isModEnabled('prcaisse') && $user->hasRight('prcaisse', 'read')) {
	$langs->load("orders");

	$sql = "SELECT c.rowid, c.ref, c.ref_client, c.total_ht, c.tva as total_tva, c.total_ttc, s.rowid as socid, s.nom as name, s.client, s.canvas";
	$sql.= ", s.code_client";
	$sql.= " FROM ".MAIN_DB_PREFIX."commande as c";
	$sql.= ", ".MAIN_DB_PREFIX."societe as s";
	$sql.= " WHERE c.fk_soc = s.rowid";
	$sql.= " AND c.fk_statut = 0";
	$sql.= " AND c.entity IN (".getEntity('commande').")";
	if ($socid)	$sql.= " AND c.fk_soc = ".((int) $socid);

	$resql = $db->query($sql);
	if ($resql)
	{
		$total = 0;
		$num = $db->num_rows($resql);

		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<th colspan="3">'.$langs->trans("DraftMyObjects").($num?'<span class="badge marginleftonlyshort">'.$num.'</span>':'').'</th></tr>';

		$var = true;
		if ($num > 0)
		{
			$i = 0;
			while ($i < $num)
			{

				$obj = $db->fetch_object($resql);
				print '<tr class="oddeven"><td class="nowrap">';

				$myobjectstatic->id=$obj->rowid;
				$myobjectstatic->ref=$obj->ref;
				$myobjectstatic->ref_client=$obj->ref_client;
				$myobjectstatic->total_ht = $obj->total_ht;
				$myobjectstatic->total_tva = $obj->total_tva;
				$myobjectstatic->total_ttc = $obj->total_ttc;

				print $myobjectstatic->getNomUrl(1);
				print '</td>';
				print '<td class="nowrap">';
				print '</td>';
				print '<td class="right" class="nowrap">'.price($obj->total_ttc).'</td></tr>';
				$i++;
				$total += $obj->total_ttc;
			}
			if ($total>0)
			{

				print '<tr class="liste_total"><td>'.$langs->trans("Total").'</td><td colspan="2" class="right">'.price($total)."</td></tr>";
			}
		}
		else
		{

			print '<tr class="oddeven"><td colspan="3" class="opacitymedium">'.$langs->trans("NoOrder").'</td></tr>';
		}
		print "</table><br>";

		$db->free($resql);
	}
	else
	{
		dol_print_error($db);
	}
}
END MODULEBUILDER DRAFT MYOBJECT */


print '</div><div class="prcaisse_customer_card">';

// Récupération du tiers
$thirdparty = new Societe($db);
$res = $thirdparty->fetch($thirdparty_id);
if ($res <= 0) {
	print $langs->trans('PleaseSelectValidThirdParty');
	print '</div>';
	llxFooter();
	$db->close();
	exit();
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

// Retourne la composition complète de la famille
$contacts = $thirdparty->contact_array();

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
	$bonsalim_list = $bonalim->fetchAll(
		'DESC',
		'date_start',
		0,
		0,
		'(beneficiary:=:' . $thirdparty->id . ') AND (status:=:' . BonAlim::STATUS_CREDITED . ')'
	);
	$bonsalim_credited = [];
	if ($bonsalim_list) {
		$bonsalim_credited = $bonsalim_list;
		$bonsalim_count = count($bonsalim_list);
		$bonsalim_total_left = 0;
		$bonsalim_total_amount = 0;
		foreach ($bonsalim_list as $b) {
			$bonsalim_total_left += $b->amount_left;
			$bonsalim_total_amount += $b->amount;
		}
		if ($bonsalim_count > 0) {
			$bonsalim_mean = (int) ($bonsalim_total_amount / $bonsalim_count);
		} else {
			$bonsalim_mean = 0;
		}
	} else {
		$bonsalim_total_left = 0;
		$bonsalim_total_amount = 0;
		$bonsalim_count = 0;
		$bonsalim_mean = 0;
	}
}

// Récupération de l'historique des bons alimentaires
if (isModEnabled('prbonalim') && $user->hasRight('bonalim@prbonalim', 'read')) {
	$bonalim = new BonAlim($db);
	$bonsalim_list = $bonalim->fetchAll(
		'DESC',
		'date_start',
		5,
		0,
		'(beneficiary:=:' . $thirdparty->id . ') AND (status:<>:' . BonAlim::STATUS_CREDITED . ')'
	);
	$bonsalim_history = [];
	if ($bonsalim_list) {
		$bonsalim_history = $bonsalim_list;
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
				'type'         => $invoice->type,
				'close_code'   => $invoice->close_code,
				'encours'      => $invoice->getRemainToPay(),
				'pos_source'   => $invoice->pos_source,
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
				'pos_source'   => $invoice->pos_source,
			];
		}
	}
}


?>
<div class="customer_infos_content">
	<div class="customer_infos_content_column">
		<div class="titre inline-block">Détail du compte</div>
		<table class="customer_infos_general">
			<tr class="date_adhesion <?php echo $date_adhesion_classes; ?>">
				<td class="label">Date de fin adhésion&nbsp;:</div>
				<?php if ($date_adhesion_fin): ?>
					<td class="value"><?php echo date('d M Y', $date_adhesion_fin); ?></div>
				<?php else: ?>
					<td class="value">&empty;</div>
				<?php endif; ?>
			</tr>
			<tr class="date_epicerie <?php echo $date_epicerie_classes; ?>">
				<td class="label">Date de fin épicerie&nbsp;:</div>
				<?php if ($date_epicerie_fin): ?>
					<td class="value"><?php echo date('d M Y', $date_epicerie_fin); ?></div>
				<?php else: ?>
					<td class="value">&empty;</div>
				<?php endif; ?>
			</tr>
			<tr class="famille">
				<td class="label">Membres du foyer&nbsp;:</div>
				<td class="value"><?php echo $nombrecontacts; ?></div>
				</tr>
			<tr class="encours <?php echo $encours_classes; ?>">
				<td class="label">Montant encours&nbsp;:</div>
				<td class="value"><?php echo price($encours['opened'], 0, $langs, 0, 0, -1, $conf->currency); ?></div>
				</tr>
			<?php if (isModEnabled('prbonalim') && $user->hasRight('bonalim@prbonalim', 'read')): ?>
			<tr class="bonalim">
				<td class="label">Bons disponibles&nbsp;:</div>
				<td class="value"><span class="bonalim_left"><?php echo price($bonsalim_total_left, 0, $langs, 0, 0, -1, $conf->currency); ?></span> <span class="bonalim_total">(<?php echo $bonsalim_count ?> &#x00D7; <?php echo $bonsalim_mean ?>)</span></div>
			</tr>
			<?php endif; ?>
			<tr class="credit <?php echo $credit_classes; ?>">
				<td class="label">Avance disponible&nbsp;:</div>
				<td class="value"><?php echo price($credit_total, 0, $langs, 0, 0, -1, $conf->currency); ?></div>
			</tr>
		</table>
		<div class="titre inline-block">Informations complémentaires</div>
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
		<div class="titre inline-block">Composition du foyer</div>
		<?php
		//show_contacts($conf, $langs, $db, $thirdparty, $_SERVER["PHP_SELF"] . '?thirdparty_id='.$thirdparty_id, 0);
		?>
		<div class="customer_contacts">
			<div class="table">
				<div class="table_row table_header">
					<div><?php echo $langs->trans('Name'); ?></div>
				</div>
				<?php foreach ($contacts as $contact_id => $name): ?>
					<div class="customer_contacts_contact table_row">
						<div class="name"><?php echo $name ?></div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
	<div class="customer_infos_content_column">
		<?php if (isModEnabled('prbonalim') && $user->hasRight('bonalim@prbonalim', 'read') && $bonsalim_credited): ?>
		<div class="titre inline-block">Bons disponibles</div>
		<div class="customer_bonalim_credited">
			<?php
			$object = array_values($bonsalim_credited)[0];
			$enabledfields = ['ref', 'amount', 'amount_left', 'issuer', 'date_start', 'date_end', 'status'];
			$arrayfields = [];

			foreach ($object->fields as $key => $val) {
				// If $val['visible']==0, then we never show the field
				if (in_array($key, $enabledfields)) {
					$visible = (int) dol_eval((string) $val['visible'], 1);
					$arrayfields[$key] = array(
						'label' => $val['label'],
						'checked' => (($visible < 0) ? 0 : 1),
						'enabled' => (abs($visible) != 3 && (bool) dol_eval($val['enabled'], 1)),
						'position' => $val['position'],
						'help' => isset($val['help']) ? $val['help'] : ''
					);
				}
			}

			$arrayfields = dol_sort_array($arrayfields, 'position');
			$objects_list = $bonsalim_credited;

			require DOL_DOCUMENT_ROOT.'/custom/prbonalim/core/tpl/bonalim_list_simple.tpl.php';
			?>
		</div>
		<?php endif; ?>
		<?php if (isModEnabled('prbonalim') && $user->hasRight('bonalim@prbonalim', 'read') && $bonsalim_history): ?>
		<div class="titre inline-block">Historique des bons (5 derniers)</div>
		<div class="customer_bonalim_history">
			<?php
			$object = array_values($bonsalim_history)[0];
			$enabledfields = ['ref', 'amount', 'amount_left', 'issuer', 'date_start', 'date_end', 'status'];
			$arrayfields = [];

			foreach ($object->fields as $key => $val) {
				// If $val['visible']==0, then we never show the field
				if (in_array($key, $enabledfields)) {
					$visible = (int) dol_eval((string) $val['visible'], 1);
					$arrayfields[$key] = array(
						'label' => $val['label'],
						'checked' => (($visible < 0) ? 0 : 1),
						'enabled' => (abs($visible) != 3 && (bool) dol_eval($val['enabled'], 1)),
						'position' => $val['position'],
						'help' => isset($val['help']) ? $val['help'] : ''
					);
				}
			}

			$arrayfields = dol_sort_array($arrayfields, 'position');
			$objects_list = $bonsalim_history;

			require DOL_DOCUMENT_ROOT.'/custom/prbonalim/core/tpl/bonalim_list_simple.tpl.php';
			?>
		</div>
		<?php endif; ?>
	</div>
	<div class="customer_infos_content_column">
		<?php if ($invoice_history): ?>
		<div class="invoice_history invoice_history_last">
			<div class="label titre">10 dernières factures</div>
			<div class="value">
				<div class="invoice_history_line table_row table_header">
					<div>Réf</div>
					<div>Date</div>
					<div>Caisse</div>
					<div>Montant</div>
					<div>À régler</div>
				</div>
				<?php foreach ($invoice_history as $line): ?>
					<div class="invoice_history_line table_row <?php if ($invoice_id && $invoice_id == $line['facid']) { echo 'active'; } ?>">
						<div class="ref"><?php echo $line['ref'] ?></div>
						<div class="date"><?php echo dol_print_date($line['date']) ?></div>
						<div class="pos_source"><?php echo getDolGlobalString('TAKEPOS_TERMINAL_NAME_' . $line['pos_source']); ?></div>
						<div class="amount">
							<div class="cell_inner">
								<?php echo ($line['type'] == Facture::TYPE_DEPOSIT ? '<span class="invoice_type invoice_type_deposit">A</span>' : ''); ?>
								<?php echo ($line['type'] == Facture::TYPE_STANDARD ? '<span class="invoice_type invoice_type_standard">D</span>' : ''); ?>
								<?php echo '<span>' . price($line['amount_gross'], 0, $langs, 0, 0, -1, $conf->currency, 0, $langs, 0, 0, -1, $conf->currency) . '</span>'; ?>
							</div>
						</div>
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
			<div class="label titre">Autres factures impayées</div>
			<div class="value">
				<div class="invoice_history_line table_row table_header">
					<div>Réf</div>
					<div>Date</div>
					<div>Caisse</div>
					<div>Montant</div>
					<div>À régler</div>
				</div>
				<?php foreach ($invoice_history_unpaid as $line): ?>
					<div class="invoice_history_line table_row <?php if ($invoice_id && $invoice_id == $line['facid']) { echo 'active'; } ?>">
						<div class="ref"><?php echo $line['ref'] ?></div>
						<div class="date"><?php echo dol_print_date($line['date']) ?></div>
						<div class="pos_source"><?php echo getDolGlobalString('TAKEPOS_TERMINAL_NAME_' . $line['pos_source']); ?></div>
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
</div>

<?php

print '</div></div>';

// End of page
llxFooter();
$db->close();
