<?php
/* Copyright (C) 2003-2006 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2014 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2015      Jean-François Ferry	<jfefe@aternatik.fr>
 * Copyright (C) 2020      Maxime DEMAREST      <maxime@indelog.fr>
 * Copyright (C) 2024		Frédéric France			<frederic.france@free.fr>
 * Copyright (C) 2024		MDW							<mdeweerd@users.noreply.github.com>
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
 *	\file       htdocs/compta/paiement/rapport.php
 *	\ingroup    invoice
 *	\brief      Payment reports page
 */

// Load Dolibarr environment
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

$action = GETPOST('action', 'aZ09');
$fileToRemove = GETPOST('removefile', 'alpha');

$socid = 0;
if ($user->socid > 0) {
	$action = '';
	$socid = $user->socid;
}

$dir = $conf->prcaisse->dir_output.'/sales';
if (!$user->hasRight('societe', 'client', 'voir')) {
	$dir .= '/private/'.$user->id; // If user has no permission to see all, output dir is specific to user
}

// Security check
if (!$user->hasRight('facture', 'lire')) {
	accessforbidden();
}

$permissiontoread = ($user->hasRight('facture', 'lire') == 1);


/*
 * Actions
 */

if ($action == 'builddoc' && $permissiontoread) {
	/*
	$rap = new pdf_paiement($db);

	$outputlangs = $langs;
	if (GETPOST('lang_id', 'aZ09')) {
		$outputlangs = new Translate("", $conf);
		$outputlangs->setDefaultLang(GETPOST('lang_id', 'aZ09'));
	}

	// We save charset_output to restore it because write_file can change it if needed for
	// output format that does not support UTF8.
	$sav_charset_output = $outputlangs->charset_output;
	if ($rap->write_file($dir, GETPOSTINT("remonth"), GETPOSTINT("reyear"), $outputlangs) > 0) {
		$outputlangs->charset_output = $sav_charset_output;
	} else {
		$outputlangs->charset_output = $sav_charset_output;
		dol_print_error($db, $rap->error);
	}
	*/

}

// Delete file from disk
if ($action == 'removedoc' && $permissiontoread && $fileToRemove) {
	/*
	$fullpathfile = dol_sanitizePathName($dir.'/'.$fileToRemove);
	$fileDirectory = dirname($fullpathfile);
	if (dol_delete_file($fullpathfile)) {
		// Delete empty directory after file deletion
		if (empty(dol_dir_list($fileDirectory))) {
			dol_delete_dir($fileDirectory);
		}
		setEventMessages($langs->trans("FileWasRemoved", $fileToRemove), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("ErrorFailToDeleteFile", $fileToRemove), null, 'errors');
	}
	*/
}


/*
 * View
 */

$formother = new Form($db);
$formfile = new FormFile($db);

llxHeader();

$titre = $langs->trans("SalesReports");
print load_fiche_titre($titre, '', 'bill');

// Formulaire de generation
print '<form method="post" action="prcaisse_report_sales.php">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="builddoc">';

$current_quarter_start_date = strtotime(date('Y') . '-' . ( ( (int)((date('n') - 1) / 3) * 3 ) + 1 ) . '-01');
$prev_quarter_start_date = strtotime('-3 months', $current_quarter_start_date);
$prev_quarter_end_date   = strtotime('-1 day', $current_quarter_start_date);
$startdate = GETPOST("startdate") ? dol_mktime(0, 0, 0, GETPOST("startdatemonth"), GETPOST("startdateday"), GETPOST("startdateyear")) : $prev_quarter_start_date;
$enddate   = GETPOST("enddate")   ? dol_mktime(23, 59, 59, GETPOST("enddatemonth"), GETPOST("enddateday"), GETPOST("enddateyear"))    : $prev_quarter_end_date;

print $langs->trans("GenerateReportSummary");

print '<br><br>';

print $langs->trans("GenerateReportFrom");
print $formother->selectDate($startdate, 'startdate');
print $langs->trans("To");
print $formother->selectDate($enddate,   'enddate');

print '<input type="submit" class="button" value="'.$langs->trans("Create").'">';
print '</form>';
print '<br>';

clearstatcache();


if ($action == 'builddoc' && $permissiontoread) {

	$result_count = [];
	$result_table = [];

	$datestart = dol_mktime(
		0, 0, 0,
		(int) GETPOST('startdatemonth'),
		(int) GETPOST('startdateday'),
		(int) GETPOST('startdateyear')
	);
	$dateend = dol_mktime(
		23, 59, 59,
		(int) GETPOST('enddatemonth'),
		(int) GETPOST('enddateday'),
		(int) GETPOST('enddateyear')
	);
	$limitdate = dol_time_plus_duree($datestart, -1, 'y');

	$entity = (int) $conf->entity;

	/**
	 * Count how many fk_soc appear in time range
	 */
	$sql = "SELECT sub.pos_source, COUNT(sub.fk_soc) as passages_foyers";
	$sql .= " FROM (";
	$sql .= "   SELECT DISTINCT";
	$sql .= "     f.fk_soc,";
	$sql .= "     f.pos_source,";
	$sql .= "     DATE_FORMAT(f.datef, '%Y-%m-%d') AS daykey";
	$sql .= "   FROM ".MAIN_DB_PREFIX."facture AS f";
	$sql .= "   INNER JOIN ".MAIN_DB_PREFIX."societe_extrafields AS se ON se.fk_object = f.fk_soc";
	$sql .= "   WHERE f.entity = ".$entity;
	$sql .= "     AND f.datef >= '".$db->idate($datestart)."'";
	$sql .= "     AND f.datef <= '".$db->idate($dateend)."'";
	$sql .= "     AND f.fk_statut > 0";
	$sql .= "     AND se.epicerie_fin >= '".$db->idate($limitdate)."'";
	$sql .= " ) AS sub";
	$sql .= " GROUP BY sub.pos_source";

	$resql = $db->query($sql);
	if (! $resql) {
		dol_print_error($db);
		exit;
	}

	while ($obj = $db->fetch_object($resql)) {
		$terminal_id = $obj->pos_source;
		$result_count[$terminal_id]['foyers_total'] = (int) $obj->passages_foyers;
	}
	$db->free($resql);

	/**
	 * Count how many each fk_soc appear in time range
	 */
	$sql = "SELECT sub.pos_source, sub.fk_soc,COUNT(daykey) as passages_foyers";
	$sql .= " FROM (";
	$sql .= "   SELECT DISTINCT";
	$sql .= "     f.fk_soc,";
	$sql .= "     f.pos_source,";
	$sql .= "     DATE_FORMAT(f.datef, '%Y-%m-%d') AS daykey";
	$sql .= "   FROM ".MAIN_DB_PREFIX."facture AS f";
	$sql .= "   INNER JOIN ".MAIN_DB_PREFIX."societe_extrafields AS se ON se.fk_object = f.fk_soc";
	$sql .= "   WHERE f.entity = ".$entity;
	$sql .= "     AND f.datef >= '".$db->idate($datestart)."'";
	$sql .= "     AND f.datef <= '".$db->idate($dateend)."'";
	$sql .= "     AND f.fk_statut > 0";
	$sql .= "     AND se.epicerie_fin >= '".$db->idate($limitdate)."'";
	$sql .= " ) AS sub";
	$sql .= " GROUP BY sub.pos_source, sub.fk_soc";

	$resql = $db->query($sql);
	if (! $resql) {
		dol_print_error($db);
		exit;
	}

	while ($obj = $db->fetch_object($resql)) {
		$terminal_id = $obj->pos_source;
		if ($obj->passages_foyers == 1) {
			@$result_count[$terminal_id]['foyers_1']++;
		}
		if ($obj->passages_foyers > 1 && $obj->passages_foyers < 5) {
			@$result_count[$terminal_id]['foyers_5']++;
		}
		if ($obj->passages_foyers >= 5) {
			@$result_count[$terminal_id]['foyers_5+']++;
		}
	}
	$db->free($resql);

	/**
	 * Count how many socpeople appear in time range
	 */
	$sql = "SELECT sub.pos_source, COUNT(sp.rowid) as passages_personnes";
	$sql .= " FROM (";
	$sql .= "   SELECT DISTINCT";
	$sql .= "     f.fk_soc,";
	$sql .= "     f.pos_source,";
	$sql .= "     DATE_FORMAT(f.datef, '%Y-%m-%d') AS daykey";
	$sql .= "   FROM ".MAIN_DB_PREFIX."facture AS f";
	$sql .= "   INNER JOIN ".MAIN_DB_PREFIX."societe_extrafields AS se ON se.fk_object = f.fk_soc";
	$sql .= "   WHERE f.entity = ".$entity;
	$sql .= "     AND f.datef >= '".$db->idate($datestart)."'";
	$sql .= "     AND f.datef <= '".$db->idate($dateend)."'";
	$sql .= "     AND f.fk_statut > 0";
	$sql .= "     AND se.epicerie_fin >= '".$db->idate($limitdate)."'";
	$sql .= " ) AS sub";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."socpeople AS sp ON sp.fk_soc = sub.fk_soc";
	$sql .= " GROUP BY sub.pos_source";

	$resql = $db->query($sql);
	if (! $resql) {
		dol_print_error($db);
		exit;
	}

	while ($obj = $db->fetch_object($resql)) {
		$terminal_id = $obj->pos_source;
		$result_count[$terminal_id]['personnes_total'] = (int) $obj->passages_personnes;
	}
	$db->free($resql);

	/**
	 * Extract detailed count data for each Soc
	 */
	$sql  = "  SELECT sub_passages.pos_source, sub_passages.fk_soc, sub_passages.nom, sub_passages.epicerie_fin, COUNT(sub_passages.fk_soc) as passages_foyers, sub_socpeople.membres_foyers";
	$sql .= "   FROM (";
	$sql .= "     SELECT DISTINCT";
	$sql .= "       f.fk_soc,";
	$sql .= "       s.nom,";
	$sql .= "       se.epicerie_fin,";
	$sql .= "       f.pos_source,";
	$sql .= "       DATE_FORMAT(f.datef, '%Y-%m-%d') AS daykey";
	$sql .= "     FROM ".MAIN_DB_PREFIX."facture AS f";
	$sql .= "     INNER JOIN ".MAIN_DB_PREFIX."societe AS s ON s.rowid = f.fk_soc";
	$sql .= "     INNER JOIN ".MAIN_DB_PREFIX."societe_extrafields AS se ON se.fk_object = f.fk_soc";
	$sql .= "     WHERE f.entity = ".$entity;
	$sql .= "       AND f.datef >= '".$db->idate($datestart)."'";
	$sql .= "       AND f.datef <= '".$db->idate($dateend)."'";
	$sql .= "       AND f.fk_statut > 0";
	$sql .= "       AND se.epicerie_fin >= '".$db->idate($limitdate)."'";
	$sql .= "   ) AS sub_passages";
	$sql .= " LEFT JOIN (";
	$sql .= "   SELECT COUNT(sp.rowid) AS membres_foyers, sp.fk_soc FROM ".MAIN_DB_PREFIX."socpeople AS sp GROUP BY sp.fk_soc";
	$sql .= " ) AS sub_socpeople ON sub_socpeople.fk_soc = sub_passages.fk_soc";
	$sql .= "   GROUP BY sub_passages.pos_source, sub_passages.fk_soc";
	$sql .= " ORDER BY pos_source, nom";

	$resql = $db->query($sql);
	if (! $resql) {
		dol_print_error($db);
		exit;
	}

	while ($obj = $db->fetch_object($resql)) {
		$terminal_id = $obj->pos_source;
		$result_table[$terminal_id][] = [
			'socid'              => $obj->fk_soc,
			'nom'                => $obj->nom,
			'epicerie_fin'       => $obj->epicerie_fin,
			'passages_foyer'     => $obj->passages_foyers,
			'membres_foyer'      => $obj->membres_foyers,
			'passages_personnes' => ((int) $obj->passages_foyers) * ((int) $obj->membres_foyers),
		];
	}
	$db->free($resql);

	print '<div class="div-table-responsive">';
	print '<table class="noborder">';

	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("Terminal").'</td>';
	print '<td class="right">'.$langs->trans("SalesCountSoc").'</td>';
	print '<td class="right">'.$langs->trans("SalesCountPeople").'</td>';
	print '</tr>';

	foreach ($result_count as $terminal_id => $count) {

		if ($terminal_id > 0) {
			$terminal_name = getDolGlobalString(
				'TAKEPOS_TERMINAL_NAME_'.$terminal_id,
				$langs->trans("TerminalName", $terminal_id)
			);
		} else {
			$terminal_name = $langs->trans("NoTerminalName");
		}

		print '<tr class="oddeven">';
		print '<td>'.$terminal_name.'</td>';
		print '<td class="right">'.$count['foyers_total'].'</td>';
		print '<td class="right">'.$count['personnes_total'].'</td>';
		print '</tr>';
	}

	print '</table>';
	print '</div>';

	print '<div class="div-table-responsive">';
	print '<table class="noborder">';

	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("Terminal").'</td>';
	print '<td class="right">'.$langs->trans("SalesCount1").'</td>';
	print '<td class="right">'.$langs->trans("SalesCount1to4").'</td>';
	print '<td class="right">'.$langs->trans("SalesCount5More").'</td>';
	print '</tr>';

	foreach ($result_count as $terminal_id => $count) {

		if ($terminal_id > 0) {
			$terminal_name = getDolGlobalString(
				'TAKEPOS_TERMINAL_NAME_'.$terminal_id,
				$langs->trans("TerminalName", $terminal_id)
			);
		} else {
			$terminal_name = $langs->trans("NoTerminalName");
		}

		print '<tr class="oddeven">';
		print '<td>'.$terminal_name.'</td>';
		print '<td class="right">'.($count['foyers_1'] ?? 0).'</td>';
		print '<td class="right">'.($count['foyers_5'] ?? 0).'</td>';
		print '<td class="right">'.($count['foyers_5+'] ?? 0).'</td>';
		print '</tr>';
	}

	print '</table>';
	print '</div>';

	foreach ($result_table as $terminal_id => $count) {

		if ($terminal_id > 0) {
			$terminal_name = getDolGlobalString(
				'TAKEPOS_TERMINAL_NAME_'.$terminal_id,
				$langs->trans("TerminalName", $terminal_id)
			);
		} else {
			$terminal_name = $langs->trans("NoTerminalName");
		}

		print '<div class="fichecenter">';

		print '<table class="centpercent notopnoleftnoright table-fiche-title">';
		print '<tr class="toptitle"><td class="nobordernopadding valignmiddle col-title"><div class="titre inline-block">'.$terminal_name.'</div></td></tr>';
		print '</table>';

		print '<div class="div-table-responsive">';
		print '<table class="noborder centpercent">';

		print '<tr class="liste_titre">';
		print '<td class="right">'.$langs->trans("ThirdPartyName").'</td>';
		print '<td class="right">'.$langs->trans("EpicerieEndDate").'</td>';
		print '<td class="right">'.$langs->trans("PeopleCount").'</td>';
		print '<td class="right">'.$langs->trans("SalesCountSoc").'</td>';
		print '<td class="right">'.$langs->trans("SalesCountPeople").'</td>';
		print '</tr>';

		foreach ($count as $row) {
			$icons = '';
			//$soc_link = dol_buildpath('/societe/card.php?socid='.$row['socid'], 1);
			//$icons .= '<a href="'.$soc_link.'">'.img_picto($langs->trans('ThirdParty'), 'fa-building', 'pictofixedwidth').'</a>';
			$prcaisse_link = dol_buildpath('/custom/prcaisse/prcaisse_customers.php?thirdparty_id='.$row['socid'], 1);
			print '<tr class="oddeven">';
			print '<td><a href="'.$prcaisse_link.'">'.img_picto($langs->trans('PRCaisseArea'), 'fa-store', 'pictofixedwidth').' '.htmlspecialchars($row['nom']).$icons.'</a></td>';
			print '<td class="right">'.htmlspecialchars($row['epicerie_fin']).'</td>';
			print '<td class="right">'.htmlspecialchars($row['membres_foyer']).'</td>';
			print '<td class="right">'.htmlspecialchars($row['passages_foyer']).'</td>';
			print '<td class="right">'.htmlspecialchars($row['passages_personnes']).'</td>';
			print '</tr>';
		}

		print '</table>';
		print '</div>';
	}
}


if (is_dir($dir.'/')) {
	print '<br>';
	print '<table width="100%" class="noborder">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("Reporting").'</td>';
	print '<td class="right">'.$langs->trans("Size").'</td>';
	print '<td class="right">'.$langs->trans("Date").'</td>';
	print '<td class="right"></td>';
	print '</tr>';

	$files = (dol_dir_list($dir.'/', 'files', 0, '^sales-[0-9]{4}[0-9]{2}[0-9]{2}-[0-9]{4}[0-9]{2}[0-9]{2}\.pdf$', '', 'name', SORT_DESC, 1));
	foreach ($files as $f) {
		$relativepath = $f['level1name'].'/'.$f['name'];
		print '<tr class="oddeven">';
		print '<td><a data-ajax="false" href="'.DOL_URL_ROOT.'/document.php?modulepart=facture_paiement&amp;file='.urlencode($relativepath).'">'.img_pdf().' '.$f['name'].'</a>'.$formfile->showPreview($f['name'], 'facture_paiement', $relativepath, 0).'</td>';
		print '<td class="right">'.dol_print_size($f['size']).'</td>';
		print '<td class="right">'.dol_print_date($f['date'], "dayhour").'</td>';
		print '<td class="right"><a href="rapport.php?removefile='.urlencode($relativepath).'&action=removedoc&token='.newToken().'">'.img_delete().'</a></td>';
		print '</tr>';
	}
	print '</table>';
}

// End of page
llxFooter();
$db->close();
