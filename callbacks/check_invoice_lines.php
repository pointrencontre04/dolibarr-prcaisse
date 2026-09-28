<?php

//if (! defined('NOREQUIREUSER'))	define('NOREQUIREUSER', '1');	// Not disabled cause need to load personalized language
//if (! defined('NOREQUIREDB'))		define('NOREQUIREDB', '1');		// Not disabled cause need to load personalized language
//if (! defined('NOREQUIRESOC'))		define('NOREQUIRESOC', '1');
//if (! defined('NOREQUIRETRAN'))		define('NOREQUIRETRAN', '1');
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
//if (!defined('NOREQUIREAJAX')) {
//	define('NOREQUIREAJAX', '1');
//}

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

header('Content-Type: application/json');

$pos_source = (int) $_SESSION['takeposterminal'];

$results = [];

// Charge les lignes
if (!$invoice->lines) {
    $invoice->fetch_lines();
}

if (!$invoice->lines) {
    echo json_encode(['status' => 'ok']);
    exit();
}

$faulty_lines = [];
foreach ($invoice->lines as $index => $line) {
    if (price2num($line->total_ttc) == 0 && !$line->remise_percent && !$line->remise) {
        $faulty_lines[] = [
            'label' => $line->libelle,
            'error' => 'price_empty',
        ];
    }
}
if ($faulty_lines) {
    echo json_encode(['status' => 'ko', 'lines' => $faulty_lines]);
    exit();
}

echo json_encode(['status' => 'ok']);
exit();
