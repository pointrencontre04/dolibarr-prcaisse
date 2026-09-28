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
 *
 * Library javascript to enable Browser notifications
 */

if (!defined('NOREQUIREUSER')) {
	define('NOREQUIREUSER', '1');
}
if (!defined('NOREQUIREDB')) {
	define('NOREQUIREDB', '1');
}
if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}
if (!defined('NOREQUIRETRAN')) {
	define('NOREQUIRETRAN', '1');
}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', 1);
}
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', 1);
}
if (!defined('NOLOGIN')) {
	define('NOLOGIN', 1);
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', 1);
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', 1);
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}


/**
 * \file    prcaisse/js/prcaisse.js.php
 * \ingroup prcaisse
 * \brief   JavaScript file for module PRCaisse.
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
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/../main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/../main.inc.php";
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

// Define js type
header('Content-Type: application/javascript');
// Important: Following code is to cache this file to avoid page request by browser at each Dolibarr page access.
// You can use CTRL+F5 to refresh your browser cache.
if (empty($dolibarr_nocache)) {
	header('Cache-Control: max-age=3600, public, must-revalidate');
} else {
	header('Cache-Control: no-cache');
}
?>

/* Javascript library of module PRCaisse */

<?php echo file_get_contents('js.cookie.min.js'); ?>

<?php echo file_get_contents('takepos.js'); ?>

var PRCaisseSwitchInvoiceType = function() {
	const callback_url = "<?php echo dol_buildpath('/prcaisse/callbacks/switch_invoice_type.php', 1); ?>";
    const invoiceid = $("#invoiceid").val();
	const params = {
		invoice_id: invoiceid,
		token: "<?php echo newToken(); ?>"
	};
    console.log("Calling invoice type switch on invoiceid="+invoiceid);
	jQuery.ajax({
		url: callback_url,
		method: 'POST',
		data: params,
		success: function(data) {
			Refresh();
		},
	});
};

var PRCaisseCloseBill = function() {
	// Check lines with empty price and no reduction
	const callback_url = "<?php echo dol_buildpath('/prcaisse/callbacks/check_invoice_lines.php', 1); ?>";
    const invoiceid = $("#invoiceid").val();
	const params = {
		invoice_id: invoiceid,
		token: "<?php echo newToken(); ?>"
	};
    console.log("Calling check invoice lines invoiceid="+invoiceid);
	jQuery.ajax({
		url: callback_url,
		method: 'POST',
		data: params,
		success: function(data_callback) {
			var data = (typeof data_callback === 'string') ? JSON.parse(data_callback) : data_callback;
			if (typeof data == "object" && data.status != 'ok') {
				const reasons = {
					price_empty: "Une des lignes de produit n'a pas de prix associ&eacute; !"
				};
				var reason = data.lines.map((line) => `<li><strong>${line.label}</strong> : ${reasons[line.error] || line.error || 'Erreur inconnue'}</li>`).join('');
				jQuery.colorbox({className: "prcaisse-cbox_closebill_alerts", html:"<h3>Impossible de valider le ticket</h3><ul>"+reason+"</ul>", width:"80%", height:"90%", transition:"none", iframe:false, title:"Alertes caisse"})
			} else {
				CloseBill();
			}
		},
	});

};