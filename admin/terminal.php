<?php
/* Copyright (C) 2004-2017  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2025		SuperAdmin
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
 * \file    prcaisse/admin/terminal.php
 * \ingroup prcaisse
 * \brief   PRCaisse setup page of a TakePOS terminal.
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
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

// Libraries
require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formsetup.class.php';
require_once '../lib/prcaisse.lib.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

// Translations
$langs->loadLangs(array("admin", "cashdesk", "prcaisse@prcaisse"));

// Initialize a technical object to manage hooks of page. Note that conf->hooks_modules contains an array of hook context
$hookmanager->initHooks(array('prcaisseterminalsetup', 'globalsetup'));

// Parameters
$action = GETPOST('action', 'aZ09');
$terminal = GETPOSTINT('terminal');
if ($terminal < 1 || $terminal > max(1, getDolGlobalInt('TAKEPOS_NUM_TERMINALS', 1))) {
	$terminal = 1;
}

// Access control
if (!$user->admin) {
	accessforbidden();
}

$formSetup = new FormSetup($db);
$formSetup->formAttributes['action'] = $_SERVER["PHP_SELF"].'?terminal='.$terminal;

// Recurring customers suggested on customer selection
$formSetup->newItem('PRCaisseSetupRecurringCustomers')->setAsTitle();

$item = $formSetup->newItem('PRCAISSE_RECURRING_CUSTOMERS_MAX'.$terminal);
$item->nameText = $langs->trans('PRCAISSE_RECURRING_CUSTOMERS_MAX');
$item->helpText = $langs->transnoentities('PRCAISSE_RECURRING_CUSTOMERS_MAXTooltip');
$item->fieldAttr['placeholder'] = '6';
$item->cssClass = 'width100';

// Reset of cash fund (scheduled job "Reset POS cash")
$formSetup->newItem('PRCaisseSetupCashReset')->setAsTitle();

$item = $formSetup->newItem('PRCAISSE_CASH_RESET_ENABLED'.$terminal)->setAsYesNo();
$item->nameText = $langs->trans('PRCAISSE_CASH_RESET_ENABLED');
$item->helpText = $langs->transnoentities('PRCAISSE_CASH_RESET_ENABLEDTooltip');

$item = $formSetup->newItem('PRCAISSE_CASH_RESET_AMOUNT'.$terminal);
$item->nameText = $langs->trans('PRCAISSE_CASH_RESET_AMOUNT');
$item->helpText = $langs->transnoentities('PRCAISSE_CASH_RESET_AMOUNTTooltip');
$item->cssClass = 'width100';

$item = $formSetup->newItem('PRCAISSE_CASH_DEPOSIT_ACCOUNT'.$terminal)->setAsSelectBankAccount();
$item->nameText = $langs->trans('PRCAISSE_CASH_DEPOSIT_ACCOUNT');
$item->helpText = $langs->transnoentities('PRCAISSE_CASH_DEPOSIT_ACCOUNTTooltip');


/*
 * Actions
 */

include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';


/*
 * View
 */

$title = "PRCaisseSetup";

llxHeader('', $langs->trans($title), '', '', 0, 0, '', '', '', 'mod-prcaisse page-admin_terminal');

// Subheader
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

// Configuration header
$head = prcaisseAdminPrepareHead();
print dol_get_fiche_head($head, 'terminal'.$terminal, $langs->trans($title), -1, "prcaisse@prcaisse");

print $formSetup->generateOutput(true);

// Page end
print dol_get_fiche_end();

llxFooter();
$db->close();
