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
 * \file    prcaisse/lib/prcaisse.lib.php
 * \ingroup prcaisse
 * \brief   Library files with common functions for PRCaisse
 */

/**
 * Prepare admin pages header
 *
 * @return array<array{string,string,string}>
 */
function prcaisseAdminPrepareHead()
{
	global $langs, $conf;

	// global $db;
	// $extrafields = new ExtraFields($db);
	// $extrafields->fetch_name_optionals_label('myobject');

	$langs->loadLangs(array("cashdesk", "prcaisse@prcaisse"));

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/prcaisse/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	// One tab per TakePOS terminal
	$numterminals = max(1, getDolGlobalInt('TAKEPOS_NUM_TERMINALS', 1));
	for ($i = 1; $i <= $numterminals; $i++) {
		$head[$h][0] = dol_buildpath("/prcaisse/admin/terminal.php", 1).'?terminal='.$i;
		$head[$h][1] = getDolGlobalString('TAKEPOS_TERMINAL_NAME_'.$i, $langs->trans("TerminalName", $i));
		$head[$h][2] = 'terminal'.$i;
		$h++;
	}

	/*
	$head[$h][0] = dol_buildpath("/prcaisse/admin/myobject_extrafields.php", 1);
	$head[$h][1] = $langs->trans("ExtraFields");
	$nbExtrafields = is_countable($extrafields->attributes['myobject']['label']) ? count($extrafields->attributes['myobject']['label']) : 0;
	if ($nbExtrafields > 0) {
		$head[$h][1] .= ' <span class="badge">' . $nbExtrafields . '</span>';
	}
	$head[$h][2] = 'myobject_extrafields';
	$h++;
	*/

	$head[$h][0] = dol_buildpath("/prcaisse/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@prcaisse:/prcaisse/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@prcaisse:/prcaisse/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, null, $head, $h, 'prcaisse@prcaisse');

	complete_head_from_modules($conf, $langs, null, $head, $h, 'prcaisse@prcaisse', 'remove');

	return $head;
}

/**
 * Check if the payment mode of a payment can be changed: the current terminal
 * made the payment, the payment was made today and the terminal is not closed.
 *
 * The terminal of a payment is the one of its invoice, as in the cash control report.
 *
 * @param	DoliDB	$db			Database handler
 * @param	object	$payment	Payment with properties module_source, pos_source and datep (database format)
 * @return	bool				True if payment mode can be changed
 */
function prcaisseIsPaymentEditable($db, $payment)
{
	global $conf;

	require_once DOL_DOCUMENT_ROOT.'/compta/cashcontrol/class/cashcontrol.class.php';

	$terminal = empty($_SESSION['takeposterminal']) ? '' : (string) $_SESSION['takeposterminal'];
	if ($terminal === '' || $payment->module_source != 'takepos' || (string) $payment->pos_source !== $terminal) {
		return false;
	}

	$now = dol_now();
	if (dol_print_date($db->jdate($payment->datep), '%Y%m%d') != dol_print_date($now, '%Y%m%d')) {
		return false;
	}

	// A month_close or day_close left empty means the whole period is closed
	$month = (int) dol_print_date($now, '%m');
	$day = (int) dol_print_date($now, '%d');
	$sql = "SELECT COUNT(rowid) as nb FROM ".MAIN_DB_PREFIX."pos_cash_fence";
	$sql .= " WHERE entity = ".((int) $conf->entity);
	$sql .= " AND posnumber = '".$db->escape($terminal)."'";
	$sql .= " AND status = ".((int) CashControl::STATUS_VALIDATED);
	$sql .= " AND year_close = ".((int) dol_print_date($now, '%Y'));
	$sql .= " AND (month_close = ".$month." OR month_close = 0 OR month_close IS NULL)";
	$sql .= " AND (day_close = ".$day." OR day_close = 0 OR day_close IS NULL)";

	$resql = $db->query($sql);
	if (!$resql) {
		return false;
	}
	$obj = $db->fetch_object($resql);

	return ($obj->nb == 0);
}
