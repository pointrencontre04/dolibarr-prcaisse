<?php
/* Copyright (C) 2023		Laurent Destailleur			<eldy@users.sourceforge.net>
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
 * \file    prcaisse/class/actions_prcaisse.class.php
 * \ingroup prcaisse
 * \brief   Example hook overload.
 *
 * TODO: Write detailed description here.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonhookactions.class.php';

/**
 * Class ActionsPRCaisse
 */
class ActionsPRCaisse extends CommonHookActions
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var string[] Errors
	 */
	public $errors = array();


	/**
	 * @var mixed[] Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var ?string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var int		Priority of hook (50 is used if value is not defined)
	 */
	public $priority;


	/**
	 * Constructor
	 *
	 *  @param	DoliDB	$db      Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Execute action completeTakePosInvoiceHeader
	 *
	 * @param	array<string,mixed>	$parameters		Array of parameters
	 * @param	CommonObject		$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string				$action			'add', 'update', 'view'
	 * @param	Hookmanager			$hookmanager	Hookmanager
	 * @return	int									Return integer <0 if KO,
	 *												=0 if OK but we want to process standard actions too,
	 *												>0 if OK and we want to replace standard actions.
	 */
	public function completeTakePosInvoiceHeader(&$parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $conf, $user;

	    $module_path = dol_buildpath('/pointrencontre/callbacks/get_encours.php', 1);
		/*
        $out = '
        $(document).ready(function() {
        	$("#topnav-left").append(\'<div id="client_encours" style="font-weight:bold; margin-top:10px;"></div>\');

            $(document).on("change", "#customer_id", function() {
                var id = $(this).val();
                if (id) {
                    $.get("' . $module_path . '?id=" + id, function(data) {
                        $("#client_encours").text("Encours : " + data + " €");
                    });
                } else {
                    $("#client_encours").text("");
                }
            });
        });
        ';

		$this->results = $out;
		$this->resprints = $out;
		*/

		$this->resprints = '<script>console.log("Hook completeTakePosInvoiceHeader exécuté");</script>';

        return 0;
    }

	/**
	 * Execute action addHtmlHeader
	 *
	 * @param	array<string,mixed>	$parameters		Array of parameters
	 * @param	CommonObject		$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string				$action			'add', 'update', 'view'
	 * @param	Hookmanager			$hookmanager	Hookmanager
	 * @return	int									Return integer <0 if KO,
	 *												=0 if OK but we want to process standard actions too,
	 *												>0 if OK and we want to replace standard actions.
	 */
	/*
	public function addHtmlHeader(&$parameters, &$object, &$action, $hookmanager)
	{
		$this->resprints = '<script>console.log("Hook addHtmlHeader exécuté");</script>';
		return 0;
	}
	*/
}
