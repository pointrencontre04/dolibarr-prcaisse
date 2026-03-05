<?php
/* Copyright (C) 2004-2017	Laurent Destailleur			<eldy@users.sourceforge.net>
 * Copyright (C) 2018-2024  Frédéric France				<frederic.france@free.fr>
 * Copyright (C) 2026		SuperAdmin
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
 * \file    prbonalim/core/boxes/prbonalimwidgetstatistics.php
 * \ingroup prbonalim
 * \brief   Widget provided by PRBonAlim
 *
 * Put detailed description here.
 */

include_once DOL_DOCUMENT_ROOT."/core/boxes/modules_boxes.php";

require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';


/**
 * Class to manage the box
 *
 * Warning: for the box to be detected correctly by dolibarr,
 * the filename should be the lowercase classname
 */
class prcaissewidgetsaleslastweek extends ModeleBoxes
{
	/**
	 * @var string Alphanumeric ID. Populated by the constructor.
	 */
	public $boxcode = "prcaisse_sales_thisweek";

	/**
	 * @var string Box icon (in configuration page)
	 * Automatically calls the icon named with the corresponding "object_" prefix
	 */
	public $boximg = "prcaisse@prcaisse";

	/**
	 * @var string Box label (in configuration page)
	 */
	public $boxlabel = 'Sales last week';

	/**
	 * @var string Box language file if it needs a specific language file.
	 */
	public $lang = 'prcaisse@prcaisse';

	/**
	 * @var string[] Module dependencies
	 */
	public $depends = array('prcaisse');

	/**
	 * @var string 	Widget type ('graph' means the widget is a graph widget)
	 */
	public $widgettype = 'graph';


	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 * @param string $param More parameters
	 */
	public function __construct(DoliDB $db, $param = '')
	{
		global $user;

		parent::__construct($db, $param);

		$this->param = $param;

		// Condition when module is enabled or not
		// $this->enabled = getDolGlobalInt('MAIN_FEATURES_LEVEL') > 0;
		// Condition when module is visible by user (test on permission)
		// $this->hidden = !$user->hasRight('prcaisse', 'myobject', 'read');
	}

	/**
	 * Load data into info_box_contents array to show array later. Called by Dolibarr before displaying the box.
	 *
	 * @param	int<0,max>	$max	Maximum number of records to load
	 * @return	void
	 */
	public function loadBox($max = 5)
	{
		global $langs, $db, $conf;

		$this->info_box_head = array('text' => $langs->trans("SalesLastWeek"));
		$this->info_box_contents = [];

		$this->info_box_contents[] = [
			0 => [
				'td'   => 'class="left liste_titre"',
				'text' => 'Terminal',
			],
			1 => [
				'td'   => 'class="right liste_titre"',
				'text' => 'Ventes',
			],
			2 => [
				'td'   => 'class="right liste_titre"',
				'text' => 'Encaissements',
			],
		];

		$stats = [];

		// Invoices created
		$sql = "
		  SELECT
		    pos_source AS terminal_id,
		    COUNT(rowid) AS sales_count
		  FROM ".MAIN_DB_PREFIX."facture
		  WHERE entity = ".((int) $conf->entity)."
		    AND fk_statut IN (" . Facture::STATUS_VALIDATED . "," . Facture::STATUS_CLOSED . ")
		    AND YEARWEEK(datef, 1) = YEARWEEK(CURDATE() - INTERVAL 1 WEEK, 1)
			AND pos_source IS NOT NULL
		  GROUP BY pos_source
		  ORDER BY pos_source";

		  $resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$stats[$obj->terminal_id] = (isset($stats[$obj->terminal_id]) ? $stats[$obj->terminal_id] : []);
				$stats[$obj->terminal_id]['sales_count'] = $obj->sales_count;
			}
		} else {
			$this->info_box_contents[] = [
				0 => [
					'td'   => 'class="left"',
					'text' => $this->db->lasterror(),
					]
				];
			}

		// Payments
		$sql = "
		  SELECT
		    f.pos_source AS terminal_id,
		    SUM(pf.amount) AS payments_ttc
		  FROM ".MAIN_DB_PREFIX."paiement AS p
		  INNER JOIN ".MAIN_DB_PREFIX."paiement_facture AS pf ON pf.fk_paiement = p.rowid
		  INNER JOIN ".MAIN_DB_PREFIX."facture AS f ON f.rowid = pf.fk_facture
		  WHERE p.entity = ".((int) $conf->entity)."
		    AND YEARWEEK(p.datep, 1) = YEARWEEK(CURDATE() - INTERVAL 1 WEEK, 1)
			AND pos_source IS NOT NULL
		  GROUP BY pos_source
		  ORDER BY pos_source";

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$stats[$obj->terminal_id] = (isset($stats[$obj->terminal_id]) ? $stats[$obj->terminal_id] : []);
				$stats[$obj->terminal_id]['payments_ttc'] = $obj->payments_ttc;
			}
		} else {
			$this->info_box_contents[] = [
				0 => [
					'td'   => 'class="left"',
					'text' => $this->db->lasterror(),
					]
				];
			}

		foreach ($stats as $terminal_id => $stat) {
			$terminal_name = getDolGlobalString('TAKEPOS_TERMINAL_NAME_'.$terminal_id, $langs->trans("TerminalName", $terminal_id));
			$this->info_box_contents[] = [
				[
					'td'   => 'class="left"',
					'text' => $terminal_name,
				],
				[
					'td'   => 'class="right"',
					'text' => $stat['sales_count'],
				],
				[
					'td'   => 'class="right amount"',
					'text' => price($stat['payments_ttc'], 0, $langs, 1, -1, -1, $conf->currency),
				],
			];
		}
	}



	/**
	 *	Method to show box.  Called when the box needs to be displayed.
	 *
	 *	@param	?array<array{text?:string,sublink?:string,subtext?:string,subpicto?:?string,picto?:string,nbcol?:int,limit?:int,subclass?:string,graph?:int<0,1>,target?:string}>   $head       Array with properties of box title
	 *	@param	?array<array{tr?:string,td?:string,target?:string,text?:string,text2?:string,textnoformat?:string,tooltip?:string,logo?:string,url?:string,maxlength?:int,asis?:int<0,1>}>   $contents   Array with properties of box lines
	 *	@param	int<0,1>	$nooutput	No print, only return string
	 *	@return	string
	 */
	public function showBox($head = null, $contents = null, $nooutput = 0)
	{
		// You may make your own code here…
		// … or use the parent's class function using the provided head and contents templates
		return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
	}
}
