<?php
/**
 * webtrees: Web based Family History software
 * Copyright (C) 2015 £ukasz Wileñski.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */
use Fisharebest\Webtrees\Controller\SimpleController;
use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\Functions\FunctionsDate;
use Fisharebest\Webtrees\GedcomTag;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Place;

/**
 * Main Report Pedigree on single Page Class for PDF
 */
class ReportPedigree extends TCPDF {
	public $DEBUGSTR        = '';
	protected $connectStyle = array(
		'parents'     => array('width' => .2, 'cap' => 'round', 'join' => 'round', 'color' => array(0, 0, 80)),   // line to parents
		'spouse'      => array('width' => .2, 'cap' => 'round', 'join' => 'round', 'color' => array(0, 0, 80)),   // line to spouse
		'descendants' => array('width' => .2, 'cap' => 'round', 'join' => 'round', 'color' => array(0, 0, 80)),   // line to descendant
		'descendant'  => array('width' => .2, 'cap' => 'round', 'join' => 'round', 'color' => array(0, 0, 80)),   // line to descendant
		'sibling'     => array('width' => .15, 'cap' => 'butt', 'join' => 'round', 'color' => array(80, 80, 160)),// line to sibling
		'nextGen'     => array('width' => .25, 'cap' => 'round', 'join' => 'round', 'color' => array(0, 0, 80)),
	);
	/**
	 * Sizes of boxes for different persons
	 * array( string boxsize => array(scale factor width, scale factor height ))
	 */
	protected $boxSizes = array();
	/**
	 * 4-dimeninal array with points for box of persons
	 *
	 * this array is set in function setOrientation()
	 *
	 * array('full|full_ref|child_full|child_full_ref|child_short|child_short_ref' =>
	 *     array('leave|inside|child'=>array(array(p1), array(p2), ... , array(pN))))
	 */
	protected $pointsBorder = array();
	protected $borderStyle  = array(
		'M' => array('width' => .1, 'cap' => 'round', 'join' => 'round', 'color' => array(64, 64, 192)),
		'F' => array('width' => .1, 'cap' => 'round', 'join' => 'round', 'color' => array(192, 66, 66)),
		'U' => array('width' => .1, 'cap' => 'round', 'join' => 'round', 'color' => array(66, 66, 66)),
		'E' => array('width' => .1, 'cap' => 'round', 'join' => 'round', 'color' => array(127, 127, 127)),  // empty
		);
	protected $fillColor = array(
		'M'                    => array(240, 240, 255),   // male
		'F'                    => array(255, 240, 240),   // female
		'U'                    => array(245, 245, 245),   // unknown
		'E'                    => array(255, 255, 255)
	);
	protected $FontSize      = 4.9;			//  | page borders
	protected $yBrim         = 20;			//  |
	protected $xBrim         = 20;			// additional space after compression
	protected $spaceCompress = 0.06;		// parents to children connection (0=child 1=parents) 	|
	protected $connectPos    = 0.6;			// line for siblings which are not displayed			| relativ to
	protected $connectChildNoDisplay=.25;	// line for siblings which are not displayed			| relativ to 
											// (from  $connectChildNoDisplay to  $connectPos)	|  $xSpace/$ySpace
	protected $radius		= 0.25;			// radius of edges for the connection lines			|
	protected $headerHeight	= 5;			// height of header
	protected $xSpace		= null;			//  |
	protected $xWidth		= null;			//  | different for 
	protected $ySpace		= null;			//  | 'portrait' and 'landscape'
	protected $yWidth		= null;			//  |
	/**
	 * scaling factor of person box in direction of same generation
	 * an array 'DISPLAYTYPE'=> size where DISPLAYTYPE is 'full' or 'short'
	 * used for display of siblings
	 */
	protected $scaleSameGen = array();
	/**
	 * scaling factor of person box in direction of next generation
	 * used for double displayed persons set in function setOrientation()
	 */
	protected $scaleNextGen      = null;
	protected $maxSpaceNoDisplay = 0.2;		// maximum space between lines when siblings are not displayed
	protected $addSpaceSiblings	 = 0;		// additional space between not displayed siblings and ancestors
	protected $allIndividuals 	 = array();	// all indiviuals which apears in pedigree tree array('gedcomXREF'=>class::Person)
	/**
	 * array (integer=> string)
	 * hold 'gedrom xref' of all ancestors or
	 * 'REFTO_gedcom xref' if individual is twice in ancestors array(int=>string 'XREF')
	 * father of $ancestors[$i] is $ancestors[$i*2], mother is $ancestors[$i*2+1]
	 * if equal null then parent of $ancestors[floor($i/2)] don't exists
	 */
	protected $ancestors		= array();
	protected $ancestorsRev		= array();	// array(string 'XREF' => integer)
	protected $families			= array();	// all families, in $families[$i] is the primary child family of $ancestors[$i] 
	protected $children			= array();	// 2-dim children of all families array(famNr=>array(ChildIDs, ...))
	protected $ancestorGen		= 1;		// how many Generation of ancestors should be shown
	protected $genShow			= 1;		// how many Generation are shown
	protected $descendantGen	= 0;		// how many Generation of descendantes should be shown
	protected $descendantNumber	= array();	// array($Cid => number)
	protected $descendantGenShow = 0;		// how many Generation of descendantes are shown
	protected $descendants		= array();	// array with all descendants of main person
	protected $scaleDescen		= 'full';
	protected $spouses			= array();	// array with spouses of descendants
											// array(XREF=>famNr=>array())
	protected $positions		= array();	// positions of direct ancestors of first person array(int number => float position)
	protected $leaves			= array();	// persons with no ancestors			  array ( =>)
	protected $leavesSort		= array();	// 
	protected $leavePositions	= array();	// positions of leaves, same style as $positions
	protected $EventsShow		= array();	// 1-dim array to store which events are displayed	array (ID=> true|false)
	protected $showSiblings		= true;		// should the siblings displayed in case of enough space in the tree
	protected $showSpouses		= false;	// should the spouses of the first person displayed
	protected $displaySiblings	= array();	// 1-dim array how the siblings of each family are displayed array( int number => false|'full'|'short')
	protected $output			= 'PDF';	// type of output 'PDF'|'HTML'
	protected $pdf				= null;		// pdf file class TCPDF
	protected $pageorient		= null;
	protected $descOffset		= 0;

	/**
	 *
	 */
	public function __construct() {
		global $controller, $vars, $showInfos, $SHOW_HIGHLIGHT_IMAGES, $SHOW_EMPTY_BOXES, $DEBUG;

		foreach (array('pid', 'fonts', 'maxgen', 'pageorient', 'compress', 'showInfos', 'showSiblings', 'showSpouses', 'boxStyle', 'connectLineWidth', 'SHOW_HIGHLIGHT_IMAGES','SHOW_EMPTY_BOXES') as $a) {
			$$a = $vars[$a]['id'];
		}
		$DEBUG = isset($vars['DEBUG']) ? (int) $vars['DEBUG']['id']	: 0;
		if ($SHOW_EMPTY_BOXES) {
			$compress = 'none';
		}
		$this->showSpouses = $showSpouses;
		$this->create($pid, $maxgen, $pageorient, $showSiblings, $compress, $fonts, $boxStyle, $connectLineWidth, $showInfos);
	}

	/**
	 * @param $pid
	 * @param $maxgen
	 * @param $pageorient
	 * @param $showSiblings
	 * @param $compress
	 * @param $fonts
	 * @param $boxStyle
	 * @param $connectLineWidth
	 * @param $showDetails
	 */
	protected function create ($pid, $maxgen, $pageorient,$showSiblings, $compress, $fonts, $boxStyle, $connectLineWidth, $showDetails) {
		global $output, $DEBUG;
		$this->boxStyle			= $boxStyle;
		$this->compress			= $compress;
		$this->connectLineWidth = $connectLineWidth;
		$this->setOrientation($pageorient, $showDetails);
		$this->showSiblings		= $showSiblings;
		$this->fonts			= $fonts;
		$this->MainPid			= $pid;
		$this->ancestorGen		= $maxgen;
		if ($this->getAllPersons($pid, $maxgen)) {
			$this->initPDF($fonts);
			$this->calcLeavePositions();
			$this->calcnoLeavesPositions();
			if ($compress == 'full') {
				$this->compressPositions($output);
				// if the compression changes the display mode of siblings the compression
				// is not total the code will be executed again
				$this->compressPositions($output);
			}
			if ($this->showSpouses != 'none') {
				$this->descendantGen	= $maxgen-1;
				$gen					= $maxgen-1;
				$nextGenIDs				= $this->getDescendants($pid, $gen, $this->positions[1]);
				while ($nextGenIDs) {
					//$this->DEBUGSTR.=count($nextGenIDs).'<br>';
					--$gen;
					$IDs = array();
					foreach ($nextGenIDs as $id => $Pos) {
						$IDs[] = $this->getDescendants($id, $gen, 0);
					}
					$nextGenIDs = array();
					foreach ($IDs as $item) {
						foreach ($item as $id => $Pos) {
							$nextGenIDs[$id] = $Pos;
						}
					}
				}
				$this->calcDescendantsPos();
				$this->setDescendantNumber($pid, 0);
				if ($this->pageorient == 'portrait') {
					$this->yOffset += max(0, $this->descOffset-$this->positions[1] + .5) * ($this->yWidth + $this->ySpace);
				} else {
					$this->xOffset += max(0, $this->descOffset-$this->positions[1] + .5) * ($this->xWidth + $this->xSpace);
				}
				$this->compressDescendants();
			}
			$this->setPageSizeandTitle($output);
			$this->displayPersons($output);
			if ($showDetails) {
				$this->displayEventInfo($output);
			}
			if ($DEBUG) {
				$this->pageBorder($output);
				$this->showHtmlInPDF(0, 0, 100, 20, 'gen_is=' . $this->descendantGenShow . ' gen_soll=' . $this->descendantGen);
				$this->showHtmlInPDF(0, 3, 500, 300, $this->DEBUGSTR);
				$this->showHtmlInPDF(50, 1, 500, 30, 'min_pos=' . (min($this->positions) - .5).
						' max_pos=' . (max($this->positions)-.5) .
						' DescOff=' . $this->descOffset . ' yoffset=' .
						(($this->yOffset-$this->headerHeight) / ($this->yWidth+$this->ySpace)));
			}
			if ($output == 'PDF') {
				$this->closePdf();
			}
		} else {
			$controller = new SimpleController();
			$controller
				->setPageTitle(I18N::translate('Pedigree - single page'))
				->pageHeader();
			echo 'internal ERROR wooc_singlepage_pedigree no person';
			exit;
		}
	}
	/**
	* Set page orientation
	* In this function set the orientation of the pedigree chart. This includes 
	* the sizes of the boxes and spaces for the persons. Also the style of of the
	* boxes depends on the orientation and will set here.
	*
	* @param string $pageorient
	* @param bool $showInfos
	* @return empty
	*/
	protected function setOrientation($pageorient, $showInfos = true) {
		$this->pageorient	= $pageorient;
		$this->descMin		= 0;
		if ($pageorient == 'portrait') {
			$this->xSpace			= 4.;
			$this->xWidth			= 47.;
			//$this->ySpace			= 1; //1.5;
			$this->ySpace			= 1.5;
			$this->yWidth			= 14.;
			$this->scaleSameGen		= array('full' => 0.8, 'short' => .6);
			$this->scaleNextGen		= 0.9;
			$this->xOffset			= 0;
			$this->yOffset			= $this->headerHeight;
			$this->pageHeightAdd	= 0;
		} else {
			$this->xSpace			= 1.5;
			$this->xWidth			= 21.;
			$this->ySpace			= 4.;
			$this->yWidth			= 25.;
			$this->scaleNextGen		= 0.85;
			$this->scaleSameGen		= array('full' => 0.9, 'short' => .75);
			$this->xOffset			= 0;
			$this->yOffset			= $this->headerHeight;
			$this->pageHeightAdd	= 0;
		}
		if (!$showInfos) {
			$this->yWidth *= 0.5;
		}
		$this->boxSizes = array(
			'full'				=> array(1.0, 1.0), 'full_ref' => array($this->scaleNextGen, 1.0),
			'spouse_short'   	=> array($this->scaleNextGen, $this->scaleSameGen['full']),
			'spouse_full'		=> array($this->scaleNextGen, 1.0),
			'spouse_de_short'	=> array($this->scaleNextGen, $this->scaleSameGen['short']),
			'spouse_de_full' 	=> array($this->scaleNextGen, $this->scaleSameGen['full']),
			'spouse_de_ref' 	=> array($this->scaleNextGen, $this->scaleSameGen['full']),
			'spouse_de_same'	=> array($this->scaleNextGen, 1), //$this->scaleSameGen['same'])
			'descendant_same'	=> array(1.0, 1), //$this->scaleSameGen['same'])
			'descendant_full'	=> array(1.0, $this->scaleSameGen['full']),
			'descendant_short'	=> array(1.0, $this->scaleSameGen['short']),
			'child_full'		=> array($this->scaleNextGen, $this->scaleSameGen['full']),
			'child_full_ref'	=> array($this->scaleNextGen + ($this->scaleNextGen - 1) * .5, $this->scaleSameGen['full']),
			'child_short'		=> array($this->scaleNextGen, $this->scaleSameGen['short']),
			'child_short_ref'	=> array($this->scaleNextGen + ($this->scaleNextGen - 1) * .5, $this->scaleSameGen['short'])
			);
		$wS = min($this->xSpace, $this->ySpace) / 4;

		foreach ($this->boxSizes as $key=>$value) {
			$H = $this->yWidth * $value[$pageorient == 'portrait' ? 1 : 0] * 0.5; // points are mirrored
			$L = $this->xWidth * $value[$pageorient == 'portrait' ? 0 : 1];
			$wH = $H / 3;
			$wL = $L / 6;
			switch($this->boxStyle){
				case 'normal':
					$this->pointsBorder[$key] =
						array(
							'empty' => array(array(0, 0, 0, $H, 0, $H),
											 array(0, $H, $L, $H, $L, $H),
											 array($L, $H, $L, 0, $L, 0)
										),
							'inside' => array(array(0, 0, 0, $H - $wS, 0, $H - $wS),
											  array(0, $H - $wS / 2, $wS / 2, $H, $wS, $H),
											  array($wS, $H, $wS / 2, $H, $L - $wS, $H),
											  array($L - $wS / 2, $H, $L, $H - $wS / 2, $L, $H - $wS),
											  array($L, $H - $wS, $L, 0, $L, 0)
										)
						);
					$this->pointsBorder[$key]['leave'] = $this->pointsBorder[$key]['inside'];
					$this->pointsBorder[$key]['child'] = $this->pointsBorder[$key]['inside'];
					break;
				default:
					$this->pointsBorder[$key] =
						array(
							'empty' => array(array(0, 0, 0, $H, 0, $H),
											 array(0, $H, $L, $H, $L, $H),
											 array($L, $H, $L, 0, $L, 0)
										),
							'leave' => array(array(-$wS, -$wH, -$wS, -$H + $wS, 0, -$H),
											 array($wS, -$H - $wS, $L / 2 - $wS, -$H - $wS,  $L / 2, -$H),
											 array($L / 2 + $wS, -$H - $wS, $L - $wS, -$H - $wS, $L, -$H),
											 array($L + $wS, -$H + $wS, $L, 0, $L + 2 * $wS, 0)
										),
							'inside' => array(array(0, -$wH, 0, -$H + $wH, -$wS, -$H - $wS),
											  array($wL, -$H, $L - $wL, -$H, $L + $wS, -$H - $wS),
											  array($L, -$H + $wH, $L, +$wH, $L, 0)
										),
							'child' => array(array(0, -$wH, -$wS, -$H + $wS, 0, -$H),
											 array($wS, -$H - $wS / 2, $L - $wS, -$H - $wS / 2, $L, -$H),
											 array($L + $wS, -$H + $wS, $L, +$wH, $L, 0)
										)
						);
					if ($L > 2 * $H) {
						$this->pointsBorder[$key]['leave'] =
							array(
								array(-$wS, -$wH, -$wS, -$H + $wS, 0, -$H),
								array($wS, -$H - $wS, $L / 3 - $wS, -$H - $wS, $L / 3, -$H),
								array($L / 3 + $wS, -$H - $wS, $L * 2 / 3 - $wS, -$H - $wS, $L * 2 / 3, -$H),
								array($L * 2 / 3 + $wS, -$H - $wS, $L - $wS, -$H - $wS, $L, -$H),
								array($L + $wS, -$H + $wS, $L, 0, $L + 2 * $wS, 0)
							);
					}
			}
		}
	}
	/**
	* get the persons from the database
	*
	* @param string $pid ID of first person
	* @param int $maxgen number of generations
	* @return true of false
	*/
	protected function getAllPersons ($pid, $maxgen) {
		if ($this->getFirstPerson($pid) != null) {
			$this->getPersons($maxgen);
			return true;
		} else {
			return false;
		}
	}
	/**
	* get the first person
	* 
	* set the variables
	* $this->ancestors		array ()
	* $this->ancestorsRev	array ()
	* $this->allIndividuals	array ()
	*
	* @param string $pid	ID of first person
	* @return Individual::getInstance($pid)
	*/
	protected function getFirstPerson($pid) {
		global $WT_TREE;
		$this->allIndividuals[$pid] = Individual::getInstance($pid, $WT_TREE);
		if ($this->allIndividuals[$pid]) {
			$this->ancestors[1] = $pid;
			$this->ancestorsRev[$pid] = 1;
			return $this->allIndividuals[$pid];
		}
		return null;
	}
	/**
	* get the persons from the database
	*
	* set the variables
	* $this->genShow		how many generation where found mayby smaller than parameter $maxgen
	* $this->ancestors	  array ()
	* $this->ancestorsRev   array ()
	* $this->allIndividuals array ()
	* $this->leaves		 array ()
	* $this->leavesSort	 array ()
	*
	* @param int $maxgen		   number of generations
	*/
	protected function getPersons($maxgen) {
		global $WT_TREE;
		for ($i = 1; $i < (1 << ($maxgen)); ++$i) {
			if ($this->ancestors[$i] != null && !preg_match('/^REFTO_/', $this->ancestors[$i])) {
				$this->families[$i] = $this->allIndividuals[$this->ancestors[$i]]->getPrimaryChildFamily();
				$this->genShow = max(floor(log($i) / log(2)) + 1, $this->genShow);
				if ($this->families[$i] != null) {
					if ($this->families[$i]->getHusband()) { // Father exists
						$id = $this->families[$i]->getHusband()->getXref();
						if (!$this->isInPedigree($id, true)) {
							if (!isset($this->allIndividuals[$id])) {
								$this->allIndividuals[$id] = Individual::getInstance($id, $WT_TREE);
							}
							$this->ancestors[2 * $i] = $id;
							$this->ancestorsRev[$id] = 2 * $i;
						} else {
							$this->ancestors[ 2 * $i] = 'REFTO_' . $id;
						}
					} else {
						$this->ancestors[ 2 * $i] = null;
					}
					if ($this->families[$i]->getWife()) { // Mother exists
						$id = $this->families[$i]->getWife()->getXref();
						if (!$this->isInPedigree($id, true)) {
							if (!isset($this->allIndividuals[$id])) {
								$this->allIndividuals[$id] = Individual::getInstance($id, $WT_TREE);
							}
							$this->ancestors[2 * $i + 1] = $id;
							$this->ancestorsRev[$id] = 2 * $i + 1;
						} else {
							$this->ancestors[ 2 * $i + 1] = 'REFTO_' . $id;
						}
					} else {
						$this->ancestors[$i * 2 + 1] = null;
					}
					if (!$this->families[$i]->getHusband() && !$this->families[$i]->getWife()) {
						$this->leaves[$i] = floor(log($i) / log(2)) + 1;
						$this->leavesSort[] = ($i * (1 << ($maxgen - $this->leaves[$i])));
					}
					if ($this->showSiblings) {
						$children_array = array();
						foreach ($this->families[$i]->getChildren() as $child) {
							$children_array[] = $child->getXref();
						}
						$this->children[$i] = $children_array;
						if (count($this->children[$i]) > 1) {
							$j = 0;
							foreach ($this->children[$i] as $key => $cId) {
								if ($this->isInPedigree($cId, true)) {
									if ($cId == $this->ancestors[$i]) {
										$this->children[$i][] = $j;
									}
								} else {
									$this->allIndividuals[$cId] = Individual::getInstance($cId, $WT_TREE);
								}
								++$j;
							}
						}
					}
				} else {
					$this->ancestors[$i * 2] = null;
					$this->ancestors[$i * 2 + 1] = null;
					$this->leaves[$i] = floor(log($i) / log(2)) + 1;
					$this->leavesSort[] = ($i * (1 << ($maxgen - $this->leaves[$i])));
				}
			} else {
				$this->ancestors[$i * 2] = null;
				$this->ancestors[$i * 2 + 1] = null;
			}
			if (preg_match('/^REFTO_/', $this->ancestors[$i])) {
				$this->leaves[$i] = floor(log($i) / log(2)) + 1;
				$this->leavesSort[] = ($i * (1 << ($maxgen-$this->leaves[$i])));
			}
		}
	}
	/**
	* check if number or Gedcom Ref is already in pedigree tree definded
	*
	* @param string $index
	* @return bool
	*/
	public function isInPedigree($index) {
		global $GEDCOM_ID_PREFIX;
		if ($GEDCOM_ID_PREFIX && preg_match ('/^' . $GEDCOM_ID_PREFIX . '/', $index)) {
			if (isset($this->ancestorsRev[$index]) || in_array($index, $this->ancestorsRev, true)) {
				return true;
			}
		} else {
			if (isset($this->ancestors[$index]) || in_array($index, $this->ancestors, true)) {
				return true;
			}
		}
		return false;
	}
	/**
	* initialise PDF file
	*
	* @param string $fonts
	* @return TCPDF class instance
	*/
	protected function initPDF($fonts) {
		global $l;

		// create new PDF document
		$pdf = new TCPDF('P', 'mm', 'a0', true, 'UTF-8', false); 
		// set document information
		$pdf->SetCreator(PDF_CREATOR);
		$pdf->SetAuthor('webtrees');
		$pdf->SetTitle('webtrees '.$this->underlinestar($this->allIndividuals[$this->ancestors[1]]->getFullName()));
		//set margins
		$pdf->SetMargins($this->xBrim, $this->yBrim, $this->xBrim);
		// remove default header/footer
		$pdf->setPrintHeader(false);
		//  $pdf->SetHeaderMargin(10);
		//  $pdf->SetHeaderData("", 10, 'a', 'STRING');
		$pdf->setPrintFooter(false);
		//set auto page breaks
		$pdf->SetAutoPageBreak(FALSE, $this->yBrim);
		//set image scale factor
		$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO); 
		//set some language-dependent strings
		$pdf->setLanguageArray($l); 
		// set font
		$pdf->SetFont($fonts, '', $this->FontSize);
		// add a page
		$pdf->AddPage();
		$this->pdf=$pdf;
	}
	/**
	* Replace CSS class "starredname" by simple "<u></u>" which TCPDF can handle
	*
	* @param string $name
	* @return string with HTML code
	*/
	public function underlinestar($name) {
		return preg_replace('#<span class="starredname">([^<]*)</span>#i', '<u>\1</u>', $name); 
	}
	/**
	* Calculate the positions of persons with no ancestors (leaves)
	* values stored in $this->leavePositions also the variables
	* $this->positions		positions of the persons
	* $this->leavesSort	array($index => ${number of person}) 
	* $this->leavesSortR	array(${number of person} => $index) 
	*
	* @return nothing
	*/
	protected function calcLeavePositions () {
		global $output, $DEBUG;
		if (!isset ($this->positions) || $this->positions==null) {
			$offset = .5;
			for ($i = (1 << ($this->genShow)) / 2; $i < (1 << ($this->genShow)); ++$i) {
				if (isset($this->ancestors[$i]) && $this->ancestors[$i] != null && !$this->isLeave($i)) {
					$this->leavesSort[] = $i;
				}
			}
			if (isset($this->leavesSort)) {
				if($output == 'HTML' && $DEBUG & 4){
					echo 'leaves=';
					print_r($this->leaves);
					echo "<br>\n";
					echo 'unsorted leavesSort=';
					print_r($this->leavesSort);
					echo "<br>\n";
				}
				sort($this->leavesSort);
				if ($output == 'HTML' && $DEBUG & 4) {
					echo 'leavesSort=';
					print_r($this->leavesSort);
					echo "<br>\n";
				}
				for ($i=0; $i < count($this->leavesSort); ++$i) {
					$j = $this->leavesSort[$i];
					while ($this->ancestors[$j] == null) {
						$j = $j / 2;
					}
					$this->positions[$j] = $i + $offset;
					$this->leavesSortR[$i] = $j;
				}
				if ($output == 'HTML' && $DEBUG & 4) {
					echo 'positions=';
					print_r($this->positions);
					echo "<br>\n";
					echo 'leavesSortR=';
					print_r($this->leavesSortR);
					echo "<br>\n"; 
				}
			}	  
			if ($this->compress != 'none') {
				$this->leavePositions = $this->positions;
			} else {
				$dummy = array();
				$nrBoxes = (1 << ($this->genShow));
				foreach($this->positions as $key => $value) {
					if ($key >= $nrBoxes / 2) {
						$dummy[$key] = $key - $nrBoxes / 2 + $offset;
					} else {
						$g = floor(log($key) / log(2)) + 1;
						$dummy[$key] = ($key - (1 << ($g)) / 2) * (1 << ($this->genShow - $g)) // space between two IND in actual generation
							+ (1 << ($this->genShow - $g)) / 2.0 - 0.5						 // position of first person in actual generation
							+ $offset;
					}
				}
				$this->leavePositions = $this->positions;
				$this->leavePositions = $dummy;
				$this->positions = $dummy;
				if ($output == 'HTML' && $DEBUG & 4) {
					echo 'dummy=';
					print_r($dummy);
					echo "<br>\n";
				}
			}
		}
	}
	/**
	* check if number in tree coresponts to a person with no ancestors 
	*
	* @param string $nr
	* @return boolean 
	*/
	protected function isLeave($nr) {
		if (isset($this->leaves[floor($nr)])) {
			return true;
		}
		return false;
	}
	/**
	* Calculate the positions of all persons
	* for these calculation the positions of persons with no ancestors should already set 
	*
	* @return nothing
	*/
	protected function calcnoLeavesPositions () {
		for ($i = (1 << ($this->genShow)) / 2 - 1; $i >= 0; --$i) {
			if ($this->isInPedigree($i, false) && !preg_match('/^REFTO_/', $this->ancestors[$i]) && !isset($this->positions[$i])) {
				$this->positions[$i] = $this->calcChildPos($i, 2 * $i, 2 * $i + 1);
			}
		}
	}
	/**
	* Calculation of the position of children. Implements individum of direct 
	* ancestor and also of siblings
	*
	* @param int $famNr			 number of family
	* @param int $father			number of father
	* @param int $mother			number of mother
	* @param int $child=-1		  which child of family (-1 ancestor of first person)
	* @return $position or null if $position of father and mother are not set
	*/
	protected function calcChildPos($famNr, $father, $mother, $child = -1) {
		if (isset($this->positions[$father]) && isset($this->positions[$mother])) {
			if ($this->showSiblings && isset($this->children) && isset($this->children[$famNr]) && count($this->children[$famNr]) > 2) {
				$nrSibl = (count($this->children[$famNr]) - 1);
				$diffParents = $this->positions[$mother] - $this->positions[$father];
				if ($child == -1) {
					$nr = $this->children[$famNr][$nrSibl];
				} else {
					$nr=$child;
				}
				if ($diffParents > 1.01) {
					foreach ($this->scaleSameGen as $type => $size) {
						$space = $size;
						if (($diffParents - (1 - $size)) > ($nrSibl - .5) * $size) {
							$this->displaySiblings[$famNr] = $type;
							$offset = ($diffParents - $space * ($nrSibl - 1) - (1 - $size)) * 0.5;
							if ($nr == $this->children[$famNr][$nrSibl]) {
								$offset += (1 - $size) / 2;
							}
							if ($nr > $this->children[$famNr][$nrSibl]) {
								$offset += (1 - $size);
							}
							return  $this->positions[$father] + $space * ($nr) + $offset;
						}
					}
				}
				$this->displaySiblings[$famNr] = false;
				$space = ($diffParents - 2 * $this->addSpaceSiblings) / ($nrSibl + 2);
				if ($space > $this->maxSpaceNoDisplay){
					$space = $this->maxSpaceNoDisplay;
				}
				if ($nr == $this->children[$famNr][$nrSibl]) {
					$add = $this->addSpaceSiblings;
				} else if ($nr < $this->children[$famNr][$nrSibl]) {
					$add = 0; //-$this->addSpaceSiblings;
				} else {
					$add = 2 * $this->addSpaceSiblings;
				}
				return $this->positions[$father] + $space * $nr + ($diffParents - $space * ($nrSibl - 1) - 2 * $this->addSpaceSiblings) * 0.5 + $add;
			} else {
				return ($this->positions[$father] + $this->positions[$mother]) / 2;
			}
		} else if (isset($this->positions[$father])) {
			if ($this->compress == 'none') {
				$g = $this->genShow - floor(log($famNr) / log(2)) - 2;
				return $this->positions[$father] + .5 * (1 << $g);
			}
			return $this->positions[$father];
		} else {
			if ($this->compress == 'none') {
				$g = $this->genShow - floor(log($famNr) / log(2)) - 2;
				if (isset($this->positions[$mother])) {
					return $this->positions[$mother] - .5 * (1 << $g);
				} else {
					return ($mother - 1) / 2 - .5 * (1 << $g);
				}
			}
			if (isset($this->positions[$mother])) {
				return $this->positions[$mother];
			} else {
				return ($mother - 1) / 2;
			}
		}
		return null;
	}
	/**
	* Compress positions of ancestors
	* the variables $this->positions and $this->leavePositions must be set and
	* the smallest position must be the first in the array 
	*
	* @param string $output	type of output (in case of 'HTML' additional DEBUG output posible)
	* @return nothing
	*/
	protected function compressPositions ($output) {
		global $DEBUG;
		$PosOffset = 0;			   // offset to old $positions
		$MaxPosition = array();
		foreach ($this->leavePositions as $key => $value) {
			$aGen = floor(log($key) / log(2)) + 1;
			if (!isset($lastKey)) { // $lastKey up to now not defined
				$lastKey = $key;
				$lastGen = $aGen;
				$positions_compr[$key] = true;
				$MaxPosition[$aGen] = $value;
				for($i = $key / 2, $g = $aGen - 1; $i > 1 && isset($this->positions[$i]) && $this->positions[$i] == $this->positions[$i * 2]; $i /=2, --$g) {
					$MaxPosition[$g]=$value;
				}
				continue;
			}
			if (abs($key - $lastKey) > $aGen){
				$h = 0;
				if ($aGen < $lastGen) {
					$h = $this->positions[$lastKey >> ($lastGen - $aGen)] - $this->positions[$key] + 1 + $this->spaceCompress - $PosOffset;
					if (isset($this->displaySiblings[$key - 1])) {
						$i = $key - 1;
						$last_per = $this->calcChildPos($i, 2 * $i, 2 * $i + 1, count($this->children[$i]) - 2);
						if ($this->displaySiblings[$i] == false) {
							$h += max($last_per - $this->positions[$i] - 0.5, 0);
						} else {
							if ($last_per != $this->positions[$i]) {
								$h += $last_per - $this->positions[$i] - (1 - $this->scaleSameGen[$this->displaySiblings[$i]]) / 2;
							}
						}
					}
					$PosOffset += $h;
				} else {
					// get the number ($i) and generation ($g) for which MaxPositions is allready set
					for ($i = $key, $g = $aGen; $i > 2 && !isset($MaxPosition[$g]); $i /= 2, --$g) {
					}
					unset($diff);
					$diff = array();
					$loopControl = ($this->positions[$i] <= $this->positions[floor($i / 2)]);
					$gStart = $g;
					for (; $i>2 && $loopControl || ($g == $gStart && $this->positions[$i] - $MaxPosition[$g] > 1); $i /= 2, --$g) {
						// this special loop control is nescesary so code is executed one time more
						$loopControl = ($this->positions[$i] <= $this->positions[floor($i / 2)]);
						if (isset($this->displaySiblings[$i])) {
							$first_child_positions = $this->calcChildPos($i, 2 * $i, 2 * $i + 1, 0);
							if ($this->displaySiblings[$i] == false) {
								$first_child_positions = min($first_child_positions + .5, $this->calcChildPos($i, 2 * $i, 2 * $i + 1));
							}
						} else {
							$first_child_positions = $this->positions[$i];
						}
						$diff[$i] = -($first_child_positions + $PosOffset - $MaxPosition[$g] - (1 + $this->spaceCompress));
						if ($this->positions[$key] + $PosOffset + $diff[$i] < 0.5){//all positions should be >0
							$diff[$i] = -($this->positions[$key] + $PosOffset) + 0.5;
						}
					}
					if (count($diff) && isset($MaxPosition[$aGen - 1]) && $this->positions[$key] + $PosOffset + max($diff) - $MaxPosition[$aGen - 1] < 0.499 + $this->spaceCompress) {
						unset ($diff);
						$diff[0] = -($this->positions[$key] + $PosOffset - $MaxPosition[$aGen - 1] - 0.5 - $this->spaceCompress);
					}
					if (count($diff)) {
						$PosOffset += max($diff);
					}
				}
			}
			if (isset($MaxPosition[$aGen])) {
				$MaxPosition[$aGen] = max($MaxPosition[$aGen], $value + $PosOffset);
			} else {
				$MaxPosition[$aGen] = $value + $PosOffset;
			}
			$this->positions[$key] += $PosOffset;
			$this->leavePositions[$key] += $PosOffset;
			$positions_compr[$key] = true;
			for ($i = $key; $i > 1 && ((($i %2) == 1 && isset($this->ancestors[$i - 1])) // exists woman and husband 
				 ||(isset($this->families[$i / 2]) && //family exists
				  !($this->families[$i / 2]->getWife() && $this->families[$i / 2]->getHusband()))); $i /= 2) {
				if (!isset($positions_compr[$i / 2])) {
					if ($this->families[$i / 2]->getWife() && $this->families[$i / 2]->getHusband()) {
						$this->positions[$i / 2] = $this->calcChildPos($i / 2, $i - 1, $i);
					} else {
						$this->positions[$i / 2] = $this->positions[$i];
					}
					$positions_compr[$i / 2] = true; //$this->positions[$i/2];
				}
			}
			for ($i = $aGen - 1; $i > 0 && isset($positions_compr[$key >> ($aGen - $i)]); --$i) {
				$p = $this->positions[$key >> ($aGen - $i)]; //+$PosOffset;
				$MaxPosition[$i] = isset($MaxPosition[$i]) ? max($MaxPosition[$i], $p) : $p;
			}
			$lastKey = $key;
			$lastGen = $aGen;
		}
		$this->positions[0] = max($MaxPosition);
	}
	/**
	* get descendants of a person and also calculates positions of spouses
	* this function is called recursiv with decreasing number of generations
	* 
	* set the variables
	*
	* @param string $pid			  ID of person
	* @param int    $generation=1     number of generation
	*
	* @return 
	*/
	function getDescendants ($pid, $generations) {
		global $WT_TREE, $DEBUG;

		$nextGenIDs = array();
		if (isset($this->descendants[$pid]['rawPos']) || $generations < 1 || !isset($this->allIndividuals[$pid])) {
			return $nextGenIDs;
		}

		$ownFams = $this->allIndividuals[$pid]->getSpouseFamilies();
		$DescendantsExist = false;

		if ($ownFams) {
			$rearange=false;
			if ($pid == $this->MainPid && count($ownFams) >= 2) {
				$rearange=true;
			}
			$sex = $this->allIndividuals[$pid]->getSex();
			$famNr = 1;
			if (!isset($this->descendants[$pid])) {
				$this->descendants[$pid] = array('rawPos' => array(), 'Gen' => 1, 'T_Pos' => 0);
			}
			if (!isset($this->spouses[$pid])){
				$this->spouses[$pid] = array();
			}
			$firstSpouseOffset = 0;

			foreach ($ownFams as $FId => $item) { //for all families
				$i = 0;
				$chFams = array();
				$children_array = array();
				$spaceSpouses = 0;
				foreach ($item->getChildren() as $child) {
					$children_array[] = $child->getXref();
				}
				foreach ($children_array as $Cid) {
					++$i;
					if (!isset($this->allIndividuals[$Cid])) {
						$this->allIndividuals[$Cid] = Individual::getInstance($Cid, $WT_TREE);
					}
					$DescendantsExist = true;
					$this->descendants[$pid]['rawPos'][$Cid] = 1 + ($i == 1 ? .5 : 0) + $spaceSpouses + (isset($this->descendants[$pid]['rawPos']) && count($this->descendants[$pid]['rawPos']) ? max($this->descendants[$pid]['rawPos']) : 0);
					$this->descendants[$Cid]['Parent'] = $pid;
					if ($this->showSpouses == 'spouse-family' && isset($this->allIndividuals[$Cid])) {
						$chFams = $this->allIndividuals[$Cid]->getSpouseFamilies();
						if (count($chFams)) {
							$this->getSpouses($Cid, $chFams);
							$spaceSpouses = count($chFams) + $this->spaceCompress;
						} else {
							$spaceSpouses = 0;
						}
					}
				}
				if ($pid == $this->MainPid) {
					$Sid = ($sex == 'M') ? $item->getWife()->getXref() : $item->getHusband()->getXref();
					if (!isset($this->allIndividuals[$Sid])) {
						$this->allIndividuals[$Sid] = ($sex=='M') ? $item->getWife() : $item->getHusband();
					}
					if ($pid == $this->ancestors[1] && $this->showSiblings && isset($this->children[1]) && count($this->children[1]) > 2) {
						if ($rearange && $famNr == 1) {
							$cOffset = $this->calcChildPos(1, 2, 3, 0) - $this->calcChildPos(1, 2, 3);
							$firstSpouseOffset = ($cOffset + 1) * $this->scaleSameGen[$this->scaleDescen];
						} else {
							$cOffset = $this->calcChildPos(1, 2, 3, count($this->children[1]) - 2) - $this->calcChildPos(1, 2, 3);
						}
					} else {
						$cOffset = 0;
					}
					if ($this->pageorient == 'portrait') {
						$xPos = 0;
						$yPos = ($famNr + $cOffset + ($rearange ? ($famNr == 1 ? -1 : -1) : 0)) * ($this->yWidth + $this->ySpace);
					} else {
						$yPos = 0;
						$xPos = ($famNr + $cOffset + ($rearange ? ($famNr == 1 ? -1 : -1) : 0)) * ($this->xWidth + $this->xSpace);
					}
					if (strlen($Sid)) {
						$this->spouses[$pid][] = array('Id' => $Sid, 'FId' => $FId, 'Fam' => $DEBUG & 256 ? 0 : $item, 'PosOffset' => array($xPos, $yPos));
					}
				}
				++$famNr;
			}
			if ($this->showSpouses != 'show_spouses' && $DescendantsExist && ($generations + $this->descendantGenShow - $this->descendantGen) < 1) {
				++$this->descendantGenShow;
				//$this->DEBUGSTR .= 'pid=' . $pid . ' g=' . $generations . '; dGS=' . $this->descendantGenShow . '; dG=' . $this->descendantGen . '<br>'."\n";
			}
			$nrChilds = isset($this->descendants[$pid]['rawPos']) ? count($this->descendants[$pid]['rawPos']) : 0;
			if ($nrChilds) {
				$min = min($this->descendants[$pid]['rawPos']);
				$max = max($this->descendants[$pid]['rawPos']) + (count($chFams) * $this->scaleSameGen[$this->scaleDescen]);
				$siblingsMarr = array();
				foreach ($this->descendants[$pid]['rawPos'] as $Cid => $pos) {
					if ($DEBUG & 256) {
						echo 'p=' . $pid . ' g=' . (1 + $this->descendantGen-$generations) . ' C=' . $Cid;
						if ((isset($this->spouses[$Cid]) && isset($this->descendants[$this->spouses[$Cid][0]['Id']]))) {
							echo ' S=' . ($this->spouses[$Cid][0]['Id']) . ' D=' . '<pre>';
							print_r($this->descendants);//$this->spouses[$Cid][0]['Id']]);
							echo '</pre>';
						}
						echo  '<br>';
					}
					if ($generations > 1) {
						if (isset($this->spouses[$Cid]) && isset($this->descendants[$this->spouses[$Cid][0]['Id']])) {
							$spID = $this->spouses[$Cid][0]['Id'];
							if (isset($this->descendants[$spID]) && !isset($siblingsMarr[$spID])) {
								$nextGenIDs[$Cid] = ($pos - ($max + $min) * 0.5);
								$siblingsMarr[$Cid] = 1;
								/*if ($DEBUG) {
									$dummy = isset($this->descendants[$Cid]['rawPos']) ? 'Yes' : 'No';	      
									$this->DEBUGSTR .= 'C=' . $Cid . ' SP=' . $spID . ' chil=' . $dummy . '<br>';
								}  */
							}
						} else {
							$nextGenIDs[$Cid] = ($pos - ($max + $min) * 0.5);
						}
					}
					$this->descendants[$pid]['rawPos'][$Cid] = 0;
					$this->descendants[$Cid]['Parent'] = $pid;
				}
				$this->descendants[$pid]['Height'] = 0;
			}
		}
		return $nextGenIDs;
	}
	/**
	* get the Spouces of an individual
	*
	* @param char $pid           GEDCOM xref of individual
	* @param array $familiyIDs   array of GEDCOM xrefs to families
	* @return empty
	*/
	function getSpouses($pid, $familyIDs) {
		global $DEBUG, $WT_TREE;

		if (isset($this->spouses[$pid])) {
			return;
		}
		$sex = $this->allIndividuals[$pid]->getSex();
		if ($this->pageorient == 'portrait') {
			$xPos = 0;
			$yPos = ($this->yWidth + $this->ySpace) * $this->scaleSameGen[$this->scaleDescen];
		} else {
			$yPos = 0;
			$xPos = ($this->xWidth + $this->xSpace) * $this->scaleSameGen[$this->scaleDescen];
		}
		$famNr = 0;
		foreach ($familyIDs as $Fid => $item) {
			++$famNr;
			if ($sex == 'M' && $item->getWife()) {
				$Sid = $item->getWife()->getXref();
			} else if ($sex == 'F' && $item->getHusband()) {
				$Sid = $item->getHusband()->getXref();
			}
			//$Sid = ($sex == 'M') ? $item->getWife()->getXref() : $item->getHusband()->getXref();
			if (isset($Sid) && strlen($Sid)) { 
				if (!isset($this->allIndividuals[$Sid])) {
					$this->allIndividuals[$Sid] = Individual::getInstance($Sid, $WT_TREE);
				}
				$this->spouses[$pid][] = array('Id' => $Sid, 'FId' => $Fid, 'Fam' => $DEBUG & 256 ? 0 : $item, // 'Gen'=>$gen,
												'PosOffset' => array($xPos * $famNr, $yPos * $famNr));
			}
		}
		return;
	}
	/**
	*  Calculate Descedants positions
	*/
	function calcDescendantsPos() {
		global $DEBUG, $output;

		if ($this->pageorient == 'portrait') {
			$boxheight = ($this->yWidth + $this->ySpace);
			$indexPos = 1;
		} else {
			$boxheight = ($this->xWidth + $this->xSpace);
			$indexPos = 0;
		}
		foreach ($this->descendants as $pid => $array) {
			if (!isset ($array['rawPos'])) {
				$this->descendants[$pid]['Height'] = $this->scaleSameGen[$this->scaleDescen];
				if (isset($this->spouses[$pid])) {
					$this->descendants[$pid]['Height'] += count($this->spouses[$pid]) * $this->scaleSameGen[$this->scaleDescen] + $this->spaceCompress;
				}
			}
			if (!isset($array['Gen'])) {
				$this->descendants[$pid]['Gen'] = $this->descendants[$array['Parent']]['Gen'] + 1;
			}
		}
        if ($DEBUG & 256 && $output != 'PDF') {
			echo '<table border="1"><tr><td valign="top"><pre>DESC=';
			print_r ($this->descendants);
			echo '<br>SPOUSE=';
			print_r ($this->spouses);
			echo '</pre></td>';
		}
		$gen = $this->descendantGenShow + 1;
		//for ($gen = $this->descendantGenShow + 1; $gen > 1; --$gen) {
			$i = 0;
			foreach (array_reverse($this->descendants) as $id => $array) {
				if (isset($array['Parent'])) { //&& $array['Gen']==$gen) {
					if (isset($this->spouses[$array['Parent']]) && count($this->spouses[$array['Parent']]) && (count($this->descendants[$array['Parent']]['rawPos']) <= count($this->spouses[$array['Parent']])) //($array['Gen'] == $gen && !isset($this->spouses[$id]))
					) {
						$nrSp = ((count($this->spouses[$array['Parent']]) + 1) * $this->scaleSameGen[$this->scaleDescen] + $this->spaceCompress) / count($this->descendants[$array['Parent']]['rawPos']);
					} else {
						$nrSp = $this->scaleSameGen[$this->scaleDescen];
					}
					$this->descendants[$array['Parent']]['Height'] += max($this->descendants[$id]['Height'], $nrSp);
					/*if ($DEBUG & 256) {
						echo 'act=' . $id  . ' gen' . $gen . ' aGen=' . $array['Gen'] . ' actH=' . $this->descendants[$id]['Height'] . ' nrSp=' .$nrSp;
						if (isset($array['Parent'])) {
							echo ' par=' . $array['Parent'];
						}
						echo '<br>';
					} */
				}
			}
		//} // for ($gen = $this->descendantGenShow; $gen >= 1; ...
		$pid = $this->MainPid;
		$pMin = 1e10;
		$pMax = -1e10;
		$MainPos = $this->positions[1];
		foreach (($this->descendants) as $pid => $array) {
			if (isset($this->descendants[$pid]['rawPos'])){
				$offset = 0;
				$i = 0;
				foreach ($this->descendants[$pid]['rawPos'] as $Cid => $Pos) {
					$this->descendants[$Cid]['T_Pos'] = $this->descendants[$pid]['T_Pos'] + $offset;
					$pMax = max($pMax, $this->descendants[$Cid]['T_Pos'] + (isset($this->spouses[$Cid]) ? count($this->spouses[$Cid]) * $this->scaleSameGen[$this->scaleDescen] : 0));
					$pMin = min($pMin, $this->descendants[$Cid]['T_Pos'] + (isset($this->spouses[$Cid]) ? count($this->spouses[$Cid]) * $this->scaleSameGen[$this->scaleDescen] : 0));
					++$i;
					if ($i == 1) {
						$this->descendants[$pid]['rawPos2'][$Cid] = 0;
						$offset = $this->descendants[$Cid]['Height'];
					} else {
						$this->descendants[$pid]['rawPos2'][$Cid] = $offset;
						$offset += $this->descendants[$Cid]['Height'];
					}
					if ($this->pageorient == 'portrait') {
						$this->descendants[$pid]['Position'][$Cid] = array(-($this->xSpace + $this->xWidth), $this->descendants[$pid]['rawPos2'][$Cid] * ($this->ySpace + $this->yWidth));
					} else {
						$this->descendants[$pid]['Position'][$Cid] = array($this->descendants[$pid]['rawPos2'][$Cid] * ($this->xSpace + $this->xWidth), ($this->ySpace + $this->yWidth));
					}
				}
			}
		}
		$dummy = 0;
		$offsetFirstGen = 0;
		$wOff = 0;
		if (isset($this->spouses[$this->MainPid]['Id'])) {
			$wOff = 0.5 * (min($this->spouses[$this->MainPid][0]['PosOffset'][$indexPos], 0) + $this->spouses[$this->MainPid][count($this->spouses[$this->MainPid]) - 1]['PosOffset'][$indexPos]);
			//$this->DEBUGSTR .= 'pmax=' . $pMax . ' wOff=' . $wOff . '<br>';
			$offsetFirstGen = $wOff - $pMax * $boxheight * .5;
		}
		if (isset($this->spouses[$this->MainPid])) {
			$spoMin = 1e10;
			$spoMax = -1e10;
			foreach ($this->spouses[$this->MainPid] as $nr => $array) {
				$pos = $array['PosOffset'][$indexPos] / $boxheight;
				$spoMin = min($spoMin, $pos);
				$spoMax = max($spoMax, $pos);
			}
			$pMax = max($spoMax, $pMax);
			$pMin = min($spoMin, $pMin);
		} else {
			$spoMax = 0;
			$spoMin = 0;
		}
		foreach (array_reverse($this->descendants) as $pid => $array) {
			if($array['Gen'] > 2 ) {
				$nrSibling = count($this->descendants[$array['Parent']]['rawPos']);
				$nrSpouses = count($this->spouses[$array['Parent']]);
				$grandpa = $this->descendants[$array['Parent']]['Parent'];
				$dummy = 0.5 * ($this->descendants[$array['Parent']]['Height']) * $boxheight;
				if ($nrSibling <= $nrSpouses && isset($this->spouses[$pid]) && count($this->spouses[$pid]) > $nrSibling && $this->descendants[$pid]['Height'] == $this->descendants[$array['Parent']]['Height']) {
					$dummy -= $boxheight;
				} else {
					if (isset($this->spouses[$array['Parent']]) && count($this->spouses[$array['Parent']])) {
						$dummy -= $this->spouses[$array['Parent']][count($this->spouses[$array['Parent']]) - 1]['PosOffset'][$indexPos];
					} else {
						$dummy -= $this->scaleSameGen[$this->scaleDescen] * .5 * ($boxheight);
					}
				}
				$this->descendants[$array['Parent']]['Position'][$pid][$indexPos] -= $dummy;
				$this->descendants[$grandpa]['Position'][$array['Parent']][$indexPos] += $dummy / $nrSibling;
			}
			if ($array['Gen'] == 2) {
				$dummy = -$offsetFirstGen;
				$this->descendants[$array['Parent']]['Position'][$pid][$indexPos] += $offsetFirstGen;
			}
		}
		if ($this->pageorient == 'portrait') {
			$this->yOffset += $dummy / 2 + $wOff; //+$boxheight/2;
		} else {
			$this->xOffset += $dummy / 2 + $wOff;
		}
		// change position of all descendants to arrange descendants and ancestors
		if ($DEBUG) {
			$maxAn = max($this->positions) + .5;
			$descHeight = ($pMin + $pMax) * 0.5;
		}
		$this->descOffset = ($pMin+$pMax) * 0.5 - ($spoMax == $spoMin ? $spoMax * .5 : $spoMax + $spoMin);
		/*
		$this->descOffset = min($this->descOffset, max($this->descendants[$this->MainPid]['rawPos2']));
		$this->descOffset = max($this->descOffset, min($this->descendants[$this->MainPid]['rawPos2']));
		*/
		if (!isset($this->positions[0])) {
			$this->positions[0] = 0;
		}
		$this->positions[0] = max($this->positions[0], $this->positions[1] + (-$this->descOffset) + $pMax);
		if ($DEBUG) {
			$this->DEBUGSTR .= 'set DESC max=' . $maxAn . ' d_o=' . ($this->descOffset) . ' diff=' . ($maxAn - $descHeight) . ' p[0]=' . $this->positions[0] . '<br>';
			if (isset($this->descendants[$this->MainPid]['rawPos2'])) {
				$this->DEBUGSTR .= 'min=' . min($this->descendants[$this->MainPid]['rawPos2']) . ' max=' . max($this->descendants[$this->MainPid]['rawPos2']) . '<br>';
			}
		}
		if (isset($this->descendants[$this->MainPid]['Position'])) {
			foreach (array_reverse($this->descendants[$this->MainPid]['Position']) as $pid => $array) {
				$this->descendants[$this->MainPid]['Position'][$pid][$indexPos] += 1 * (-$this->descOffset) * ($boxheight);
			}
		}
		if ($DEBUG & 256 && $output != 'PDF') {
			echo '<td><pre>DESC=';
			print_r($this->descendants);
		    echo '<br>SPOUSE=';  
			print_r($this->spouses);
			echo '</pre></td></tr></table><br>';
		}
	}
	/**
	* Set page size and title
	*
	* global variables:
	* $SHOW_EMPTY_BOXES
	*
	* @param string $output	type of output
	* @return empty
	*/
	protected function setPageSizeandTitle($output) {
		global $SHOW_EMPTY_BOXES;

		if ($output == 'PDF') {
			if ($SHOW_EMPTY_BOXES) {
				$max = (1 << ($this->genShow - 1));
			} else {
				$max = max($this->positions) + .5;
			}
			if (isset($this->spouses[$this->MainPid]) && $max < 1 + count($this->spouses[$this->MainPid])) {
				$this->DEBUGSTR .= 'set PAGESIZE max=' . $max. ' f=' . '<br>';
				$max = 1 + count($this->spouses[$this->MainPid]);
			}
			if ($this->pageorient == 'portrait') {
				$this->xOffset += $this->descendantGenShow * ($this->xWidth + $this->xSpace) + ($this->showSpouses != 'none' ? $this->xSpace : 0);
				$h = 2 * $this->yBrim + $this->yOffset + ($max) * ($this->yWidth + $this->ySpace);
				$w = 2 * $this->xBrim + $this->xOffset + ($this->genShow) * ($this->xWidth + $this->xSpace);
				$dO = 'L';
				$dX = $this->xBrim + $this->xOffset;
				$dY = $h - $this->yBrim - $this->FontSize / 2.5;
				if ($h > $w) {
					$orient='P'; //luk
					$dX = $dX * 1.6;
				} else {
					$orient = 'L';
				}
			} else {
				$h = 2  *$this->yBrim + $this->headerHeight + ($this->genShow + $this->descendantGenShow) * ($this->yWidth + $this->ySpace) + $this->pageHeightAdd + $this->ySpace;
				$w = 2 * $this->xBrim + $this->xOffset + ($max) * ($this->xWidth + $this->xSpace);
				$dO = 'R';
				$dX = $w - $this->yBrim - ($this->xWidth + $this->xSpace);
				$dY = $this->yBrim + $this->genShow * ($this->yWidth + $this->ySpace) + $this->headerHeight - $this->FontSize / 2.5; // + $this->pageHeightAdd;
				if ($h > $w) {
					$orient = 'P';
				} else {
					$orient='L';
					$dY = $dY * 2;
				}
			}
			$this->pdf->setPageFormat(array($w, $h), $orient);
			//actual date
			$this->pdf->MultiCell ($this->xWidth, $this->FontSize / 2.5,
				 FunctionsDate::timestampToGedcomDate(mktime(0, 0, 0, date("m"), date("d"), date("Y")))->Display(), 0, $dO, 0, 0, $dX, $dY, true, 0, true, false);
			//title
			$this->pdf->MultiCell($w, $this->FontSize * 2, "<h2>" . I18N::translate('%1$s: %2$d Generation Pedigree Chart', $this->underlinestar($this->allIndividuals[$this->ancestors[1]]->getFullName()), max($this->genShow, $this->descendantGenShow + 1)) . "</h2>", 0, 'C', 0, 0, 0, $this->yBrim, true, 0, true, false, $this->FontSize * 2);
		} else {
			$this->FontSize = 10;
			$controller = new SimpleController();
			$controller
				->setPageTitle(I18N::translate('Pedigree - single page'))
				->pageHeader();
			echo '<br><center> <table border="0">' . "\n";
			echo ' <tr><th class="list_label">&nbsp;</th>
					<th class="list_label">' . I18N::translate('Generation') . '</th>
					<th class="list_label">' . I18N::translate('Name') . '</th>
					<th class="list_label">' . I18N::translate('See person') . '</th>
					<th class="list_label">' . I18N::translate('Siblings') . '</th>
					<th class="list_label" colspan="3">' . GedcomTag::getLabel('BIRT') . '</th>
					<th class="list_label" colspan="2">' . GedcomTag::getLabel('DEAT') . '</th>
				</tr>' . "\n";
		}
	}
	/**
	* Display all Persons
	*
	*
	* @param string $output	type of output
	* @return nothing
	*/
	protected function displayPersons($output) {
		global $SHOW_EMPTY_BOXES;

		if ($output == 'PDF' && $this->showSpouses != 'none' && isset($this->descendants[$this->MainPid])) {
			if ($this->pageorient == 'portrait') {
				$MainxPos = $this->xBrim + $this->xOffset;
				$MainyPos = $this->yBrim + $this->yOffset + $this->positions[1] * ($this->ySpace + $this->yWidth);
			} else {
				$MainxPos = $this->xBrim + $this->xOffset + $this->positions[1] * ($this->xSpace + $this->xWidth);
				$MainyPos = $this->yBrim + $this->yOffset + $this->genShow * ($this->ySpace + $this->yWidth);
			}
			$this->displayDescendant($this->MainPid, $MainxPos, $MainyPos);
		}
		for ($i = 1; $i < (1 << ($this->genShow)); ++$i) {
			if (isset($this->ancestors[$i]) && $this->ancestors[$i] != null ) {
				$aGen = floor(log($i) / log(2));
				if ($output == 'PDF') {
					if ($this->pageorient == 'portrait') {
						$a = $this->xBrim + $this->xOffset + ($this->xWidth + $this->xSpace) * $aGen;
						$b = $this->yBrim + $this->yOffset + ($this->yWidth + $this->ySpace) * $this->positions[$i];
					} else {
						$a = $this->xBrim + $this->xOffset + ($this->xWidth + $this->xSpace) * $this->positions[$i];
						$b = $this->yBrim + $this->yOffset + ($this->yWidth + $this->ySpace) * ($this->genShow - $aGen - 1) + $this->ySpace;
					}
					if ($this->showSiblings && isset($this->children[$i]) && count($this->children[$i]) > 2) {
						$n =(count($this->children[$i]) - 1);
						if (isset($this->positions[$i * 2]) && isset($this->positions[$i * 2 + 1])) {
							for ($j = 0; $j < $n; ++$j) {
								if ($j != $this->children[$i][$n]) {
									if ($this->pageorient == 'portrait') {
										$pX = $a;
										$pY = $this->calcChildPos($i, 2 * $i, 2 * $i + 1, $j) * ($this->yWidth + $this->ySpace) + $this->yBrim + $this->yOffset;
										$lY = $pY;
										$lX2 = $a + $this->xWidth + $this->xSpace * $this->connectPos;
										$lY2 = $pY;
									} else {
										$pX = $this->calcChildPos($i, 2 * $i, 2 * $i + 1, $j) * ($this->xWidth + $this->xSpace) + $this->xBrim + $this->xOffset;
										$pY = $b - ($this->yWidth * (1 - $this->scaleNextGen));
										$lX = $pX;
										$lX2 = $pX;
										$lY2 = $b - $this->ySpace * $this->connectPos;
									}
									$dispaySibl = false;
									if (isset($this->displaySiblings[$i]) && $this->displaySiblings[$i] != false) {
										$dispaySibl = true;
										$boxSize = 'child_' . $this->displaySiblings[$i];
										if ($this->isInPedigree($this->children[$i][$j], true)) {
											$boxSize .= '_ref';
										}
										if ($this->pageorient == 'portrait') {
											$lX = $a + $this->xWidth;
										} else {
											$lY = $b;
										}
									} else {
										if ($this->pageorient == 'portrait') {
											$lX = $a + $this->xWidth + $this->xSpace * $this->connectChildNoDisplay;
										} else {
											$lY = $b - $this->ySpace * $this->connectChildNoDisplay;
										}
									}
									$this->connectPersons('sibling', $lX, $lY, $lX2, $lY2, $aGen);
									if ($dispaySibl) {
										$this->pdfPerson($boxSize, $pX, $pY, $this->children[$i][$j], sprintf ("%d%c. ", $i, $j + 97));
									}
								}
							}
						}
					}
					if ($this->pageorient == 'portrait') {
						if ($aGen < $this->genShow) {
							foreach (array($i * 2, $i * 2 + 1) as $j) {
								if(isset($this->positions[$j])) {
									$this->connectPersons('parents', $a + $this->xWidth, $b, $a + $this->xWidth + $this->xSpace, 
											$this->yBrim + $this->yOffset + $this->positions[$j] * ($this->yWidth + $this->ySpace), $aGen);
								}
							}
						}
						if ($aGen == $this->genShow - 1 && !$this->isLeave($i)) {
							$this->connectPersons('nextGen', $a + $this->xWidth, $b, $a + $this->xWidth + $this->xSpace * $this->connectPos, $b, $aGen);
						}
						if (preg_match('/^REFTO_/', $this->ancestors[$i]) && isset($this->families[$this->GedcomRefToNumber($this->ancestors[$i])])) {
							if ($this->TwoParents($this->GedcomRefToNumber($this->ancestors[$i]))) {
								$this->connectPersons('parents', $a + $this->xWidth * ($this->scaleNextGen), $b, 
										$a + $this->xWidth + $this->xSpace * $this->connectChildNoDisplay, $b - $this->yWidth / 3., $aGen);
								$this->connectPersons('parents', $a + $this->xWidth * ($this->scaleNextGen), $b,
										$a + $this->xWidth + $this->xSpace * $this->connectChildNoDisplay, $b + $this->yWidth / 3., $aGen);
							} else {
								$this->connectPersons('parents', $a + $this->xWidth * ($this->scaleNextGen), $b,
										$a + $this->xWidth + $this->xSpace * $this->connectChildNoDisplay, $b, $aGen);
							}
						}
					} else {
						if ($aGen < $this->genShow) {
							foreach (array($i * 2, $i * 2 + 1) as $j) {
								if(isset($this->positions[$j])) {
									$this->connectPersons('parents', $a, $b, $this->xBrim + $this->xOffset + ($this->xWidth + $this->xSpace) * $this->positions[$j], $b-$this->ySpace, $aGen);
								}
							}
						}
						if ($aGen == $this->genShow - 1 && !$this->isLeave($i)) { 
							$this->connectPersons('nextGen', $a, $b, $a, $b - $this->ySpace * $this->connectPos, $aGen);
						}
						if (preg_match('/^REFTO_/', $this->ancestors[$i]) && isset($this->families[$this->GedcomRefToNumber($this->ancestors[$i])])) {
							if ($this->TwoParents($this->GedcomRefToNumber($this->ancestors[$i]))) {
								$this->connectPersons('parents', $a, $b + ($this->yWidth * (1 - $this->scaleNextGen)), $a - $this->xWidth / 3.,
										$b - $this->ySpace * $this->connectChildNoDisplay, $aGen);
								$this->connectPersons('parents', $a, $b + ($this->yWidth * (1 - $this->scaleNextGen)), $a + $this->xWidth / 3.,
										$b - $this->ySpace * $this->connectChildNoDisplay, $aGen);
							} else {
								$this->connectPersons('parents', $a, $b + ($this->yWidth * (1 - $this->scaleNextGen)), $a,
										$b - $this->ySpace * $this->connectChildNoDisplay, $aGen);
							}
						}
					}
					if ($SHOW_EMPTY_BOXES && $aGen < $this->genShow - 1 && !$this->TwoParents($i)) {
						if (!preg_match('/^REFTO_/', $this->ancestors[$i])) {
							$fullDist = (1 << (($this->genShow - $aGen - 1))) / 2.0 * (($this->pageorient == 'portrait') ? ($this->yWidth + $this->ySpace) : $this->xWidth + $this->xSpace);
							for ($g = $this->genShow - 2; $g >= $aGen; --$g) { // not set generation start with largest because of overlap
								$firstAnc = isset($this->families[floor($i)]) && $this->families[floor($i)]->getHusband() ? (1 << ($g - $aGen + 1)) / 2 : 0;
								$lastAnc = isset($this->families[floor($i)]) && $this->families[floor($i)]->getWife() ? (1 << ($g - $aGen + 1)) / 2 : (1 << ($g - $aGen + 1));
								for ($n = $firstAnc; $n < $lastAnc; ++$n) { // individuals per generation
									if ($this->pageorient == 'portrait') { // order of condition and loops optimised for reading not for speed
										$perDist = (1 << (($this->genShow - $g - 1))) * ($this->yWidth + $this->ySpace) / 2.0;
										$xoff = ($this->xWidth + $this->xSpace) * ($g - $aGen + 1);
										$yoff = -$this->yWidth / 2. - $fullDist + ($n + .5) * $perDist;
										$xoffL = $xoff;
										$yoffL = -$fullDist + 2 * floor($n / 2.) * $perDist + $perDist;
										$xoffLen = $this->xSpace;
										$yoffLen = $perDist / 2. * ($n % 2 ? -1 : 1);
									} else {
										$perDist = (1 << (($this->genShow - $g - 1))) * ($this->xWidth + $this->xSpace) / 2.0;
										$xoff = -$this->xWidth / 2. - $fullDist + ($n + .5) * $perDist;
										$yoff = -($this->yWidth + $this->ySpace) * ($g - $aGen + 1);
										$xoffL = -$fullDist + 2 * floor($n / 2.) * $perDist + $perDist + $perDist * ($n % 2 ? 0.5 : -0.5);
										$yoffL = $yoff + ($this->yWidth + $this->ySpace);
										$xoffLen = $perDist / 2. * ($n % 2 ? 1 : -1);
										$yoffLen = $this->ySpace;
									}
									$this->connectPersons('parents', $a + $xoffL - $xoffLen, $b + $yoffL, $a + $xoffL, $b + $yoffL - $yoffLen, $g);
									$this->personBorder('full', $a + $xoff , $b + $yoff, 'E', $i);
								}
							}
						} else {
							//how to handle this case ???
						}
					}
					if (preg_match('/^REFTO_/', $this->ancestors[$i])) {
						$ref = substr($this->ancestors[$i], 6);
						$boxsize = 'full_ref';
					} else {
						$ref = $this->ancestors[$i];
						$boxsize = 'full';
					}
					$this->pdfPerson($boxsize, $a, $b, $ref, $i . ". ");
				}
				if ($output == 'HTML') {
					if (!preg_match('/^REFTO_/', $this->ancestors[$i])) {
						$id = $this->ancestors[$i];
						$how = 'full';
					} else {
						$id = substr($this->ancestors[$i], 6);
						$how = false;
					}
					$this->printPersonHtml($how, $id, $i, '');
					if ($this->showSiblings && isset($this->children[$i]) && count($this->children[$i]) > 2) {
						$n = (count($this->children[$i]) - 1);
						for ($j = 0; $j < $n; ++$j) {
							if ($j != $this->children[$i][$n]) {
								if ($this->isInPedigree($this->children[$i][$j], true)) {
									$how = 'double';
								} else {
									$how='full';
								}
								$this->printPersonHtml($how, $this->children[$i][$j], $i, sprintf('%c', $j + 97));
							}
						}
					}
				}
			}
		}
		if ($output == 'HTML') {
			echo '</table><br>';
		}
	}
	/**
	* Display decsendant
	*
	* @param $pid
	* @param $MainxPos
	* @param $MainyPos
	* @param int $gen
	* @return nothing
	*/
	protected function displayDescendant($pid, $MainxPos, $MainyPos, $gen = 0) {
		if ($this->pageorient == 'portrait') {
			$yOffset       = 0;
			$SpouseyOffset = 0;
			$SpouseYOff    = 0;
			$spLineYOff    = 0;
			$SpChildYOff   = 0;
			$lineXOffset   = $this->xWidth;
			$lineXLength   = $this->xSpace * ($this->connectPos);
			$lineXOffChi   = $this->xSpace * (1 - $this->connectPos);
			$lineYOffChi   = 0;
			$lineYLength   = 0;
		} else {
			$yOffset       = -($this->yWidth);
			$SpouseyOffset = -$this->scaleNextGen * $this->yWidth;
			$SpouseYOff    = ($this->yWidth) * (1 - $this->scaleNextGen) + $yOffset;
			$spLineYOff    = $this->yWidth;
			$SpChildYOff   = $this->yWidth + $this->ySpace * (1 - $this->connectPos);
			$lineXOffset   = 0;
			$lineXOffChi   = 0;
			$lineYOffChi   = -$this->ySpace * (1 - $this->connectPos);
			$lineXLength   = 0;
			$lineYLength   = -$this->ySpace * ($this->connectPos);
		}
		if ($this->showSpouses == 'spouse-family' || $this->showSpouses == 'children'){
			$nrChilds = isset($this->descendants[$pid]['Position']) ? count($this->descendants[$pid]['Position']) : 0;
			if ($nrChilds > 1) {
				if (true) { //XXX only in case of no spouses
					$this->connectPersonsOffset('nextGen', $MainxPos-$lineXOffChi, $MainyPos - $lineYOffChi, $lineXOffChi, $lineYOffChi, $gen + 1);
				}
				$a = $this->descendants[$pid]['Position'];
				$b = array_slice($a, 0, 1);
				$c = array_slice($a, -1, 1);
				$first = array_shift($b);
				$last = array_shift($c);
				$this->connectPersons('descendants', $MainxPos + $lineXOffset + $first[0], $MainyPos + $first[1] + $yOffset, $MainxPos + $lineXOffset + $last[0], $MainyPos + $last[1] + $yOffset, $gen);
			}
			$i = 0;
			if (isset($this->descendants[$pid]['Position'])) {
				foreach ($this->descendants[$pid]['Position'] as $Cid => $pos) {
					if (isset($this->descendants[$Cid]['Position'])) {
						$this->displayDescendant($Cid, $MainxPos + $pos[0], $MainyPos + $pos[1], $gen + 1);
						if ($this->showSpouses == 'children' && count($this->descendants[$Cid]['Position']) > 1) {
							$this->connectPersonsOffset('nextGen', $MainxPos + $pos[0] - $lineXLength * (1 - $this->connectPos) / ($this->connectPos), $MainyPos + $pos[1] + $SpChildYOff + $yOffset, $lineXLength * (1 - $this->connectPos) / ($this->connectPos), $lineYLength * (1 - $this->connectPos) / ($this->connectPos), $gen + 1);
						}
					}
					++$i;
					if ($i != 1 && $i != $nrChilds) {
						$w = $this->lineWidth('nextGen', $gen);
						$l = 1 - .5 * $w[1];
						$this->connectPersonsOffset('descendant', $MainxPos + $pos[0] + $lineXOffset, $MainyPos + $pos[1] + $yOffset, $lineXLength * $l, $lineYLength * $l, $gen);
					}
					if ($nrChilds == 1) {
						unset($dummy);
						switch ($this->showSpouses) {
							case 'children':
								$dummy = $this->connectPos;
								break;
							case 'spouse-family':
								if (!isset($this->spouses[$Cid][0])) {
									$dummy = $this->connectPos;
								}
								break;
							default:
								$dummy = 1.0;
								break;
						}
						if (isset($dummy)) {
							$this->connectPersonsOffset('descendant', $MainxPos + $pos[0] + $lineXOffset, $MainyPos + $pos[1] + $yOffset,$lineXLength / ($dummy), $lineYLength / ($dummy), $gen);
						} else {
							if ($this->pageorient == 'portrait') {
								$xl = $lineXLength / $this->connectPos;
								$yl = -$this->descendants[$this->descendants[$Cid]['Parent']]['Position'][$Cid][1];
							} else {
								$xl = -$this->descendants[$this->descendants[$Cid]['Parent']]['Position'][$Cid][0];
								$yl = $lineYLength / $this->connectPos;
							}
							$this->connectPersonsOffset('descendant', $MainxPos + $pos[0] + $lineXOffset + $xl, $MainyPos + $pos[1] + $yOffset + $yl, -$xl, -$yl, $gen);
						}
					}
					if (isset($this->spouses[$Cid])) {
						foreach (array_reverse($this->spouses[$Cid]) as $nr => $spouse) {
							if (($nr = $this->getDescendantNumber($spouse['Id']))) {
								$how = 'spouse_de_' . 'ref';
								// show additional line if children shown some where else
								if (isset($this->descendants[$spouse['Id']]['rawPos']) && count($this->descendants[$spouse['Id']]['rawPos'])) {
									if ($this->pageorient == 'portrait') {
										$xo = -$this->xSpace;
										$yo = ($this->yWidth + $this->ySpace) * .5 * $this->scaleSameGen[$this->scaleDescen];
										$lineXL = $this->xSpace * (1 - $this->connectPos);
										$lineYL = 0;
									} else {
										$yo = $this->ySpace * ($this->connectPos);
										$xo = ($this->xWidth + $this->xSpace) * .5 * $this->scaleSameGen[$this->scaleDescen];
										$lineXL = 0;
										$lineYL = $this->ySpace * (1 - $this->connectPos);
									}
									$this->connectPersonsOffset('nextGen', $MainxPos + $pos[0] + $xo, $MainyPos + $pos[1] + $yo, $lineXL, $lineYL, $gen + 1);
								}
							} else {
								$how='spouse_de_' . $this->scaleDescen;
							}
							$this->connectPersonsOffset('spouse', $MainxPos + $pos[0], $MainyPos + $pos[1] + $spLineYOff + $yOffset, $spouse['PosOffset'][0], $spouse['PosOffset'][1], $gen + 1);
							$this->pdfPerson($how, $MainxPos + $pos[0] + $spouse['PosOffset'][0], $MainyPos + $pos[1] + $spouse['PosOffset'][1] + $SpouseYOff, $spouse['Id'], '', $Cid);
						}
					}
					$this->pdfPerson('descendant_' . $this->scaleDescen, $MainxPos + $pos[0], $MainyPos + $pos[1] + $yOffset, $Cid, $this->getDescendantNumber($Cid) . '. ');
				}
			}
		}
		if ($pid == $this->MainPid) {
			if ($this->showSpouses == 'spouse-family' || $this->showSpouses == 'show_spouses') {
				foreach (array_reverse($this->spouses[$pid]) as $item) {
					$this->connectPersonsOffset('spouse', $MainxPos, $MainyPos, $item['PosOffset'][0], $item['PosOffset'][1], $gen);
					$this->pdfPerson('spouse_full', $MainxPos + $item['PosOffset'][0], $MainyPos + $item['PosOffset'][1] + $SpouseyOffset, $item['Id'], '', $pid);
				}
			} else {
				if ($this->pageorient == 'portrait') {
					$lineXLength = $this->xSpace * (1 - $this->connectPos);
					$lineYLength = 0;
				} else {
					$lineXLength = 0;
					$lineYLength = -$this->ySpace * (1 - $this->connectPos);
				}
				$this->connectPersonsOffset('nextGen', $MainxPos - $lineXLength, $MainyPos - $lineYLength, $lineXLength, $lineYLength, $gen);
			}
		}
	}
	/**
	* Connect two different persons in TCPDF file
	* connection style is stored in the array $this->connectStyle
	* the line thickness increases with decreasing generation if
	* $this->connectLineWidth is > 0.0
	*
	* @param string $how	style of connection 'sibling'|'parents'|'nextGen'
	* @param float $x1	first x-position
	* @param float $y1	first y-position
	* @param float $x2	second x-position
	* @param float $y2	second y-position
	* @param int gen		generation default=0
	*/
	protected function connectPersonsOffset($how, $x1, $y1, $dx, $dy, $gen = 0) {
		$this->connectPersons($how, $x1, $y1, $x1 + $dx, $y1 + $dy, $gen);
	}
	/**
	* @param $how
	* @param $x1
	* @param $y1
	* @param $x2
	* @param $y2
	* @param int $gen
	*/
	protected function connectPersons ($how, $x1, $y1, $x2, $y2, $gen = 0) {
		$circ = 0.35; //maximal 0.5
		$w = $this->lineWidth($how, $gen);
		$w1 = $w[0];
		$w2 = $w[1];
		$mirrorV = false;
		$mirrorH = false;
		$rotate = false;
		$lengthNeg = false;
		if ($this->pageorient == 'portrait') {
			$h = ($y2 - $y1);
			$dG =($x2 - $x1);
		} else {
			$rotate = true;
			$h = ($x2 - $x1);
			$dG = ($y1 - $y2);
		}
		if ($dG < 0) {
			$lengthNeg = true;
			$dG = -$dG;
		}
		$l = 2 * min($this->connectPos, $this->radius) * $dG;
		$d1 = $this->connectPos * $dG - $l * 0.5;
		$d2 = $dG - ($d1 + $l);
		/*// connect to next generation
		if ($this->pageorient == 'portrait') {
			if ($dG == $this->xSpace) {
				$d2 += $this->xWidth;
			}
		} else {
			if ($dG == $this->ySpace) {
				$d2 += $this->yWidth;
			}
		} */
		if ($h < 0) {
			$mirrorV = true;
			$h = -$h;
		}
		if ($how == 'spouse' || $how == 'descendants') {
			$dG = (($this->pageorient == 'portrait') ? $this->xSpace : $this->ySpace);
			$l = min($this->connectPos, $this->radius) * $dG;
			$d1 = $this->connectPos * $dG - $l;
			$d2 = $dG - 2 * $l - $d1;
			$circ *= 2.0; // different meaning of $l
			if ($how == 'spouse') {
				$w2 = $w1;
			} else {
				$w1 = $w2;
				$d2 = $d1;
			}
			$points = array(array(0, -$w1, $d2, -$w1, $d2, -$w1)	// Line 1
			, array($d2 + $circ * ($l + $w2), -$w1, $d2 + $l + $w2, $l - $w1 + $w2 - $circ * ($l + $w2), $d2 + $l + $w2, $l - $w1 + $w2)	//C2
			, array($d2 + $l + $w2, $l - $w1 + $w2, $d2 + $l + $w2, $h - $l + $w1 - $w2, $d2 + $l + $w2, $h - $l + $w1 - $w2)		//L3
			, array($d2 + $l + $w2, $h - $l + $w1 - $w2 + $circ * ($l + $w2), $d2 + $circ * ($l + $w2), $h + $w1, $d2, $h + $w1)	//C4
			, array($d2, $h + $w1, 0, $h + $w1, 0, $h + $w1)	//L5
			, array(0, $h + $w1, 0, $h - $w1, 0, $h - $w1)		//L6
			, array(0, $h - $w1, $d2, $h - $w1, $d2, $h - $w1)	//L7
			, array($d2 + $circ * ($l - $w2), $h - $w1, $d2 + $l - $w2, $h - $l - $w1 + $w2 + $circ * ($l - $w2), $d2 + $l - $w2, $h - $l - $w1 + $w2)	//C8
			, array($d2 + $l - $w2, $h - $l - $w2, $d2 + $l - $w2, $l - $w2 + $w1, $d2 + $l - $w2, $l - $w2 + $w1)	//L9
			, array($d2 + $l - $w2, $l - $w2 + $w1 - $circ * ($l - $w2), $d2 + $circ * ($l - $w2), $w1, $d2, $w1)	//C10
			, array($d2, $w1, 0, $w1, 0, $w1)	//L11
			, array(0, $w1, 0, 0, 0, 0)			//L12
			);
		} else {
			if (abs($h) >= $l + $w1 - $w2) {
				$points = array(array(0, -$w1, $d1, -$w1, $d1, -$w1)	// Line 1
				, array($d1 + $circ * ($w2 + $w2 + $l), -$w1, $d1 + ($w2 + $w2 + $l) * 0.5, (-$w2 - $w2 + $l) * (0.5 - $circ), $d1 + ($w2 + $w2 + $l) * 0.5, (-$w2 - $w2 + $l) * 0.5)	//C2
				, array($d1 + ($w2 + $w2 + $l) * 0.5, (-$w2 - $w2 + $l) * 0.5, $d1 + ($w2 + $w2 + $l) * 0.5, (-$w2 - $w2 + $l) * 0.5, $d1 + ($w2 + $w2 + $l) * 0.5, $h - $w2 - ($l - $w2 - $w2) * 0.5)	//L3
				, array($d1 + ($w2 + $w2 + $l) * 0.5, $h - $w2 - ($l - $w2 - $w2) * (0.5 - $circ), $d1 + $l + $circ * ($w2 + $w2 - $l), $h - $w2, $d1 + $l, $h - $w2)	//C4
				, array($d1 + $l, $h - $w2, $d1 + $l, $h - $w2, $d1 + $l + $d2, $h - $w2)	//line 5
				, array($d1 + $l + $d2, $h - $w2, $d1 + $l + $d2, $h - $w2, $d1 + $l + $d2, $h + $w2)	//line 6
				, array($d1 + $l, $h + $w2, $d1 + $l, $h + $w2, $d1 + $l, $h + $w2)	//line 7
				, array($d1 + $l - $circ * ($l + $w1 + $w2), $h + $w2, $d1 + ($l - $w2 - $w2) * .5, $h + $w2 - ($l + $w2 + $w2) * (0.5 - $circ), $d1 + ($l - $w2 - $w2) * .5, $h + $w2 - ($l + $w2 + $w2) * .5)	//C8
				, array($d1 + ($l - $w2 - $w2) * .5, ($l - $w2 - $w2) * .5, $d1 + ($l - $w2 - $w2) * .5, $w1 - $w2 + ($l - $w2 - $w2) * .5, $d1 + ($l - $w2 - $w2) * .5, $w1 - $w2 - $w2 * 0 + ($l) * .5)	//L9
				, array($d1 + ($l - $w2 - $w2) * .5, $w1 - (4 * $w2 - $l) * (0.5 - $circ), $d1 + $circ * ($l - $w2 - $w2), $w1, $d1 + 0, $w1)         //C10 XXX wrong???
				, array($d1, $w1, $d1 + 0, $w1, 0, $w1)	//Line 11
				, array(0, $w1, 0, $w1, 0, -$w1)		//line 12
				);
			} else {
				if ($h == 0) {
					if ($how == 'nextGen') {
						$w2 = $w1;
					} else {
						$w1 = $w2;
					}
				}
				$a1 = 0;
				$b1 = -$w1;
				$a2 = $d1;
				$b2 = -$w2 + $h;
				$a3 = $dG - $d2;
				$b3 = $w2 + $h;
				$a4 = $dG;
				$b4 = $w1;
				$points = array(array($a1, $b1, $a2, $b1, $a2, $b1)  // L
				, array($a2 + $circ * ($a3 - $a2), $b1, .5 * ($a3 + $a2), .5 * ($b2 + $b1), .5 * ($a3 + $a2), .5 * ($b2 + $b1))// C
				, array(.5 * ($a3 + $a2), .5 * ($b2 + $b1), $a3 + $circ * ($a2 - $a3), $b2, $a3, $b2) // C
				, array($a3, $b2, $a4, $b2, $a4, $b2) // L
				, array($a4, $b2, $a4, $b3, $a4, $b3) // L
				, array($a4, $b3, $a3, $b3, $a3, $b3) // L
				, array($a3 + $circ * ($a2 - $a3), $b3, .5 * ($a2 + $a3), .5 * ($b4 + $b3), 0.5 * ($a2 + $a3), 0.5 * ($b4 + $b3)) // C
				, array(.5 * ($a2 + $a3), .5 * ($b4 + $b3), $a2 + $circ * ($a3 - $a2), $b4, $a2, $b4) // C
				, array($a2, $b4, $a1, $b4, $a1, $b4) // L
				, array($a1, $b4, $a1, $b1, $a1, $b1) // L
				);
			}
		}
		$this->pdf->StartTransform();
		$this->pdf->Translate($x1, $y1);
		if ($how == 'spouse') {
			$this->pageorient == 'portrait' ? $this->pdf->MirrorH(0) : $this->pdf->MirrorV(0);
		}
		if ($mirrorV) {
			if ($this->pageorient == 'portrait') {
				$this->pdf->MirrorV(0);
			} else {
				$this->pdf->MirrorH(0);
			}
		}
		if ($lengthNeg) {
			$this->pageorient == 'portrait' ? $this->pdf->MirrorH(0) : $this->pdf->MirrorV(0);
		}
		if ($rotate) {
			$this->pdf->Rotate(90, 0, 0);
		}
		$this->pdf->Polycurve(0, -$w1, $points, 'DF', $this->connectStyle[$how], array(210));
		$this->pdf->StopTransform();
	}
	/**
	* @param $how
	* @param $gen
	*
	* @return array
	*/
	protected function lineWidth ($how, $gen) {
		$w1 = min($this->connectPos, $this->radius) * max($this->xSpace, $this->ySpace) * $this->connectLineWidth;
		if ($how == 'sibling') {
			$w1 *= .5;
		} else {
			$w1 *= (1 - 0.5 * ($gen / (max(1, $this->genShow - 1, $this->descendantGenShow)))); // line thickness depends on generation firstGen=100% lastGen=50%
		}
		return array($w1, $w1 * .75);
	}
	/**
	* @param
	*
	* @return string
	*/
	protected function getDescendantNumber($Cid) {
		if (isset($this->descendantNumber[$Cid])) {
			return '<font color="#777">{' . ($this->descendantNumber[$Cid]) . '}</font>';
		}
		return  '';
	}
	/**
	* @param $pid
	* @param $number
	*
	* @return int
	*/
	protected function setDescendantNumber($pid, $number) {
		$i = $number;
		$this->descendantNumber[$pid] = $i++;
		for ($gen = 1; $gen <= $this->descendantGenShow; ++$gen) { // XXX not correct $gen=1
			foreach ($this->descendants as $key => $item) {
				if ($item['Gen'] == $gen) {
					if (isset($this->descendants[$key]['rawPos'])) {
						foreach ($this->descendants[$key]['rawPos'] as $Cid => $pos) {
							$this->descendantNumber[$Cid] = $i++;
						}
					}
				}
			}
		}
		return $i;
	}
	/**
	* Display a Person in an TCPDF::MultiCell
	*
	* global variables:
	*  $PEDIGREE_SHOW_GENDER
	*  $DEBUG
	*
	*
	* @param string $boxSize Size of Box /(spouse|descendant|child_)?(same|full|short)_(ref)?/
	* @param float $x x Position of Box
	* @param float $y y Position of Box
	* @param string $id person id from GEDCOM File
	* @param string $nr =""    number relativ to first person
	* @param string $Fid FamilyId neaded for boxsize spouse
	*/
	protected function pdfPerson($boxSize, $x, $y, $id, $nr = '', $Fid = '') {
		global $DEBUG, $WT_TREE, $SHOW_HIGHLIGHT_IMAGES, $PEDIGREE_SHOW_GENDER, $showInfos;

		//Zend_Session::writeClose();
		$xWidth = $this->xWidth * $this->boxSizes[$boxSize][$this->pageorient == 'portrait' ? 0 : 1];
		$yWidth = $this->yWidth * $this->boxSizes[$boxSize][$this->pageorient == 'portrait' ? 1 : 0];
		if (!isset($this->allIndividuals[$id])) {
			return;
		}
		$l_person = $this->allIndividuals[$id];
		$indifacts = $l_person->getFacts();
		$showFacts = array('OCCU', 'RELI');
		$p_info = '';
		if ($DEBUG & 32) {
			if ($this->pageorient == 'portrait') {
				$debugstr = " y=" . (($y - $this->yBrim - $this->yOffset) / ($this->yWidth + $this->ySpace) - 0.5);
				$debugstr .= " yoff=" . ($this->yOffset - $this->headerHeight) / ($this->yWidth + $this->ySpace);
			} else {
				$debugstr = " x=" . (($x - $this->xBrim - $this->xOffset) / ($this->xWidth + $this->xSpace) - 0.5);
				$debugstr .= " xoff=" . ($this->xOffset) / ($this->xWidth + $this->xSpace);
			}
		} else {
			$debugstr = '';
		}
		if ($this->pageorient == 'portrait') {
			$y -= ($yWidth / 2.0);
		} else {
			$x -= ($xWidth / 2.0);
			if (preg_match('/short|ref/', $boxSize) && !preg_match('/spouse/', $boxSize)) { // XXX check for better regex
				$y += ($this->yWidth - $yWidth);
			}
		}
		// sex information
		$sex = $l_person->getSex();
		if ($PEDIGREE_SHOW_GENDER) {
			$imgSex = $this->sexImage($sex);
		} else {
			$imgSex = '';
		}
		// marriage information displayed in case of person is female and no child
		$fam = false;
		if (preg_match('/spouse/', $boxSize) && isset($this->spouses[$Fid])) {
			foreach ($this->spouses[$Fid] as $item) {
				if ($item['Id'] == $id) {
					$fam = $item['Fam'];
				}
			}
		}
		if (!preg_match('/child/', $boxSize) && ((isset($this->families[floor($nr / 2)]) && ($sex == 'F')) || (preg_match('/spouse/', $boxSize) && $fam))) {
			if (!preg_match('/spouse/', $boxSize)) {
				$fam = $this->families[floor($nr / 2)];
			}
			$mdate = $fam->getMarriageDate()->Display();
			$mplace = '';
			foreach ($fam->getAllMarriagePlaces() as $n => $marriage_place) {
				$wt_place = new Place($marriage_place, $WT_TREE);
				$mplace = $wt_place->getShortName();
				break;
			}
			$what = (strlen($mdate) > 7 ? 2 : 0)  /* XXX why >7 ?? */ + (strlen($mplace) > 0 ? 1 : 0);
			if ($what) {
				$marrstr = $this->getEventImg('marr', $l_person) . $mdate . ($what % 2 && $what & 2 ? ' - ' : '') . $mplace . '; ';
			} else {
				$marrstr = '';
			}
		} else {
			$marrstr = '';
		}
		if ((preg_match('/full/', $boxSize) || preg_match('/same/', $boxSize) ) && !preg_match('/ref/', $boxSize)) { // XX not efficent one preg_match enought??
			//Birth information
			$bdate = $l_person->getBirthDate();
			$Dstr = $bdate != null ? $bdate->Display() . ' ' : '';
			$wt_place = new Place($l_person->getBirthPlace(), $WT_TREE);
			$Pstr = $wt_place->getShortName();
			$what = (strlen($Dstr) > 7 ? 2 : 0)  /* XXX why >7 ?? */ + (strlen($Pstr) > 0 ? 1 : 0);
			$birth = $this->getEventImg('birt', $l_person) . $Dstr . $this->parents_age($id) . 
			($what % 2 && $what & 2 ? ' - ' : '') . // date AND place exists
			($what % 2 ? $Pstr : '') . ($what > 0 ? '; ' : '');
			// Death information
			if ($l_person->isDead()) {
				$ddate = $l_person->getDeathDate();
				$Dstr = $bdate != null ? $ddate->Display() . ' ' : '';
				$wt_place = new Place($l_person->getDeathPlace(), $WT_TREE);
				$Pstr = $wt_place->getShortName();
				$age = Date::GetAge($bdate, $ddate, 1);
				$what = (strlen($Dstr) > 7 ? 2 : 0)  /* XXX why >7 ?? */ + (strlen($Pstr) > 0 ? 1 : 0);
				$death = $this->getEventImg('deat', $l_person) . ($what > 0 ?	// date OR place exists
				($what & 2 ?   // date exists
				$Dstr . ($age > 0 ? '(' . I18N::translate('Age') . ' ' . Date::GetAge($bdate, $ddate, 2) . ') ' : '') : '') .
				($what % 2 && $what & 2 ? ' - ' : '') . // date AND place exists
				$Pstr : I18N::translate('yes') ). ';';
			} else {
				$death='';
			}
			if (preg_match('/child/', $boxSize)) {
				if ($this->pageorient == 'portrait') {
					$x += ($this->xWidth - $xWidth);
				} else {
					$y += ($this->yWidth - $yWidth);
				}
			}
			$this->personBorder($boxSize, $x, $y, $sex, $nr);
			$supported_types = array('', 'gif', 'jpg', 'jpeg', 'png', 'swf', 'psd', 'bmp', 'tif', 'tiff', 'jpc', 'jp2', 'jpx', 'jb2', 'swc', 'iff', 'wbmp', 'xbm');
			if ($SHOW_HIGHLIGHT_IMAGES && ($media = $l_person->findHighlightedMedia()) && in_array($media->extension(), $supported_types)) {
				$img = $media->getServerFilename('thumb');
				$space = min($this->xSpace, $this->ySpace) * 0.5; // XXX not a nice style to set
				if (file_exists($img)) {
					$imgSize = getimagesize($img);
					if  ($xWidth / $yWidth > 3) {
						$s = min($xWidth, $yWidth) / max(1, $imgSize[1] / $imgSize[0]);
					} else {
						$s = min($xWidth, $yWidth) / max(1, $imgSize[1] / $imgSize[0]) / 4;
					}
					$this->pdf->Image($img, $x + $space, $y + $space, $s - 2 * $space, ($s - 2 * $space) * $imgSize[1] / $imgSize[0]);
					$xWidth -= $s;
					$x += $s;
				}
			}
			// occupation and other facts
			foreach ($indifacts as $key => $item) {
				$tag = $item->getTag();
				if (in_array($tag, $showFacts)) {
					$p_info .= ' ' . $this->getEventImg($tag, $l_person) . ' ' . $item->getValue();
				}
			}
			if ($p_info) {
				$p_info .= '<br>';
			}
			$html = '<font size="+1"><b>' . $nr . $this->underlinestar($l_person->getFullName()) . '</b></font>' . $imgSex . ' ' . '<br>';
			if ($showInfos) {
				$html .= $p_info . $birth . $marrstr . $death;
			}
			$this->showHtmlInPDF($x - .5, $y + .4, $xWidth, $yWidth, $html . $debugstr);
		} else {
			//Birth information
			$Dstr = $l_person->getBirthYear();
			$Pstr = '';
			$what = (isset($Dstr) ? 2 : 0);
			$birth = $this->getEventImg('birt', $l_person) . $Dstr . $this->parents_age($id) .
				($what % 2 && $what & 2 ? ' - ' : '') . // date AND place exists
				($what % 2 ? $Pstr : '') . ($what > 0 ? '; ' : '');
			//Death information
			if ($l_person->isDead()) {
				$Dstr = $l_person->getDeathYear();
				$Pstr = '';
				$age = Date::GetAge($l_person->getBirthDate(), $l_person->getDeathDate(), 1);
				$what = (isset($Dstr) ? 2 : 0);
				$death = $this->getEventImg('deat', $l_person) . ($what > 0 ?	// date OR place exists
					($what & 2 ?   // date exists
					$Dstr . ($age > 0 ? ' ('.I18N::translate('Age') . ' ' . Date::GetAge($l_person->getBirthDate(), $l_person->getDeathDate(), 2).') ' : '') : '') .
					($what % 2 && $what & 2 ? ' - ' : '') . // date AND place exists
					$Pstr : I18N::translate('yes'));
			} else {
				$death = '';
			}
			if ($this->pageorient == 'portrait') {
				if (preg_match('/child/', $boxSize)) {
					$x += ($this->xWidth - $xWidth);
				}
				$xWidth *= $this->scaleNextGen;
			} else {
				if (preg_match('/child_.*_ref/', $boxSize)) {
					$y -= $yWidth * (1 - $this->boxSizes[$boxSize][1]);
				}
			}
			$ref = false;
			if (preg_match('/ref/', $boxSize)) {
				$ref = true;
			}
			$html = '<font size="+1"><b>' . $nr . $this->underlinestar($l_person->getFullName()) . '</b></font> ' . (!$ref ? $imgSex : '') . '<br>';
			if ($showInfos) {
				$html .= $p_info . (preg_match('/short_ref/', $boxSize) ? '' : ($birth || $death || $marrstr ? $birth . $marrstr . $death . '<br>' : '')
				//. (isset($marrstr) && strlen($marrstr) > 0 ? $marrstr . '<br>' : '')
				) .	($ref ? I18N::translate('see person %s', $this->GedcomRefToNumber($id)) : '');
			} elseif ($ref) {
				$html .= I18N::translate('see person %s', $this->GedcomRefToNumber($id));
			}
			$this->personBorder($boxSize, $x, $y, $sex, $nr);
			$this->showHtmlInPDF($x - .5, $y + .4, $xWidth, $yWidth, $html. $debugstr);
		}
	}
	/**
	* create HTML output of sexImage in a way which TCPDF can handle 
	*
	* @param string $sex
	* @return string with HTML code
	*/
	public function sexImage($sex) {
		$pedigree_module = new WoocSinglePagePedigreeModule(__DIR__);
		$imgs = array('M' => WT_MODULES_DIR . $pedigree_module->getName() . '/images/sex_m.png',
					  'F' => WT_MODULES_DIR . $pedigree_module->getName() . '/images/sex_f.png',
					  'U' => WT_MODULES_DIR . $pedigree_module->getName() . '/images/sex_u.png');
		if ($sex == 'M' || $sex == 'F') {
			$imgSex = '<img alt="" src="' . $imgs[$sex] . '" height="' . (0.8 * $this->FontSize) . '" >';
		} else {
			$imgSex = '<img alt="" src="' . $imgs['U'] . '" height="' . (0.8 * $this->FontSize) . '" >';
		}
		return $imgSex;
	}
	/**
	* Create HTML output for Image of an Event
	*
	* @parm $event	which event one of birt|deat (not case sensitive) 
	* @parm $person=null  which person
	* @return string	  Html code of image or empty string
	*/
	private function getEventImg($event, $person = null) {
		if ($person != null) {
			if (strtolower($event) == 'birt') {
				if ($person->getAllEventDates('CHR') || $person->getAllEventPlaces('CHR')) {
					$event='chr';
				}
				if ($person->getAllEventDates('BIRT') || $person->getAllEventPlaces('BIRT')) {
					$event='birt';
				}
				else if ($event != 'chr') {
					$event='';
				}
			} else if (strtolower($event) == 'deat') {
				if ($person->getAllEventDates('BURI') || $person->getAllEventPlaces('BURI')) {
					$event='buri';
				}
				if ($person->getAllEventDates('DEAT') || $person->getAllEventPlaces('DEAT')) {
					$event='deat';
				}
			}
		} else {
			// ?
		}
		$pedigree_module = new WoocSinglePagePedigreeModule(__DIR__);
		$imgs = array(0 => WT_MODULES_DIR . $pedigree_module->getName() . '/images/birth.png',
					  1 => WT_MODULES_DIR . $pedigree_module->getName() . '/images/chris.png',
					  2 => WT_MODULES_DIR . $pedigree_module->getName() . '/images/occu.png',
					  3 => WT_MODULES_DIR . $pedigree_module->getName() . '/images/reli.png',
					  4 => WT_MODULES_DIR . $pedigree_module->getName() . '/images/marr.png',
					  5 => WT_MODULES_DIR . $pedigree_module->getName() . '/images/death.png',
					  6 => WT_MODULES_DIR . $pedigree_module->getName() . '/images/buri.png');
		$names = array( 0 => GedcomTag::getLabel('BIRT'),
						1 => GedcomTag::getLabel('CHR'),
						2 => GedcomTag::getLabel('OCCU'),
						3 => GedcomTag::getLabel('RELI'),
						4 => GedcomTag::getLabel('MARR'),
						5 => GedcomTag::getLabel('DEAT'),
						6 => GedcomTag::getLabel('BURI'));
		switch (strtolower($event)) {
			case 'birt':
				$i = 0;
				break;
			case 'chr':
				$i = 1;
				break;
			case 'occu':
				$i = 2;
				break;
			case 'reli':
				$i = 3;
				break;
			case 'marr':
				$i = 4;
				break;
			case 'deat':
				$i = 5;
				break;
			case 'buri':
				$i = 6;
				break;
			default:
				break;
		}
		if (isset($i)) {
			$this->EventsShow[$i] = true;	 // which images are used
			$imgSize = getimagesize($imgs[$i]);
			return '<img src="' . $imgs[$i] . '" height="' . (0.8 * $this->FontSize) . '" width="' . ($this->FontSize * .8) * $imgSize[0] / $imgSize[1] . '" >&nbsp;';
		}
		if (strtolower($event) == 'all')  {
			$str = '<b></b>'; //='<table>';
			for($i = 0; $i < count($imgs); ++$i) {
				if(isset($this->EventsShow[$i])) { // only print used images
					$imgSize = getimagesize($imgs[$i]);
					$str .= '<img alt="' . GedcomTag::getLabel('BIRT') . '" src="' . $imgs[$i] . '" height="' . (0.8*$this->FontSize) . 	    '" width="' . ($this->FontSize * .8) * $imgSize[0] / $imgSize[1] . '" > &nbsp; ' . $names[$i] . '<br>';
				}
			}
			return $str;
		}
		return '';
	}
	/**
	* Format age of parents in HTML
	*
	* global variables:
	* $SHOW_PARENTS_AGE, $WT_TREE;
	* 
	* @param string $pid string child ID
	* @return string with HTML code
	*/
	public function parents_age($pid) {
		global $SHOW_PARENTS_AGE, $WT_TREE;

		$html = '';
		if ($SHOW_PARENTS_AGE) {
			$person = Individual::getInstance($pid, $WT_TREE);
			$families = $person->getChildFamilies();
			// Multiple sets of parents (e.g. adoption) cause complications, so ignore.
			$birth_date = $person->getBirthDate();
			if ($birth_date->isOK() && count($families) == 1) {
				$family = current($families);
				// Allow for same-sex parents
				foreach (array($family->getHusband(), $family->getWife()) as $parent) {
					if ($parent && $parent->getBirthDate()->isOK()) {
						$html .= $this->sexImage($parent->getSex()) . Date::GetAge($parent->getBirthDate(), $person->getBirthDate(), 2);
					}
				}
			}
		}
		return $html;
	}
	/**
	* Display box in which the person is displayed
	*
	* @param $boxSize
	* @param $x
	* @param $y
	* @param $sex
	* @param $nr
	*/
	protected function personBorder($boxSize, $x, $y, $sex, $nr) {
		$xWidth = $this->xWidth * $this->boxSizes[$boxSize][$this->pageorient == 'portrait' ? 0 : 1];
		$yWidth = $this->yWidth * $this->boxSizes[$boxSize][$this->pageorient == 'portrait' ? 1 : 0];
		if (preg_match("/^child/i", $boxSize)) {
			$type = 'child';
		} else {
			if ($this->isLeave($nr)) {
				$type = 'leave';
			} else {
				$type = 'inside';
			}
		}
		if ($sex == 'E') {
			$type = 'empty';
		}
		$this->pdf->StartTransform();
		$this->pdf->Translate($x, 1.0 * $y + $yWidth * 0.5);
		if ($this->pageorient == 'landscape') {
			$this->pdf->Rotate(90, $xWidth * 0.5, 0);
			$this->pdf->Scale(100.0 * $yWidth / $xWidth, 100.0 * $xWidth / $yWidth, $xWidth * 0.5, 0);
		}
		$this->pdf->Polycurve(0, 0, $this->pointsBorder[$boxSize][$type], 'DF', $this->borderStyle[$sex], $this->fillColor[$sex]);
		$this->pdf->MirrorV(0);
		$this->pdf->Polycurve(0, 0, $this->pointsBorder[$boxSize][$type], 'DF', $this->borderStyle[$sex], $this->fillColor[$sex]);
		$this->pdf->StopTransform();
	}
	/**
	* display html code in TCPDF file
	* if the text don't fit to the box, the font size is reduced
	*
	* @param float $x	   x-Position of text
	* @param float $y	   y-Position of text
	* @param float $xWidth  length of text box
	* @param float $yWidth  height of text box
	* @param string $html   html code do display
	* @return float scaling factor
	*/
	protected function showHtmlInPDF($x, $y, $xWidth, $yWidth, $html) {
		//int MultiCell(float $w, float $h, string $txt, [mixed $border = 0], [string $align = 'J'], [int $fill = 0], [int $ln = 1], [float $x = ''], [float $y = ''], [boolean $reseth = true], [int $stretch = 0], [boolean $ishtml = false], [boolean $autopadding = true], [float $maxh = 0]);
		$this->pdf->startTransaction();
		$this->pdf->MultiCell($xWidth, 0, $html, 0, 'L', 0, 1, $x, $y, true, 0, true, true);
		$newY = $this->pdf->getY();
		if ($newY - $y <= $yWidth ) { // check if text fits into box
			$this->pdf->commitTransaction(true);
			return 1.0;
		} else {
			$this->pdf->rollbackTransaction(true);
			$scale = $yWidth / ($newY - $y);
			$this->pdf->SetFont($this->fonts, '', $this->FontSize * $scale);
			$this->pdf->MultiCell($xWidth, 0, $html, 0, 'L', 0, 1, $x, $y, true, 0, true, true);
			$this->pdf->SetFont($this->fonts, '', $this->FontSize);
			return $scale;
		}
	}
	/**
	* convert Gedcom Ref to number in pedigree tree
	*
	* @param mixed $ref
	* @return string
	*/
	public function GedcomRefToNumber($ref) {
		if (preg_match ('/^REFTO_/', $ref)) {
			return $this->ancestorsRev[substr($ref, 6)];
		} else {
			if (isset($this->ancestorsRev[$ref])) {
				return $this->ancestorsRev[$ref];
			} else if (($str = $this->getDescendantNumber($ref)) != '') {
				return $str;
			} else {
				return false;
			}
		}
		return false;
	}
	/**
	* check if for person with number $nr two parents set in the database
	*
	* @param integer $nr
	* @return boolean 
	*/
	protected function TwoParents($nr) {
		if (!isset($this->ancestors[floor($nr)])) {
			return false;
		}
		if ($this->isLeave($nr)) {
			return false;
		}
		if (isset($this->families[$nr]) && !($this->families[$nr]->getHusband() && $this->families[$nr]->getWife())) {
			return false;
		}
		return true;
	}
	/**
	* HTML output from a person
	* 
	* global variables:
	* $WT_TREE, $DEBUG 
	*
	* @param string $how		'full'|'ref'
	* @param string $ref		GEDCOM xref
	* @param int $i			 number of person
	* @param string $child=''   string additionaly displayed for siblings
	*
	* @return nothing
	*/
	protected function printPersonHtml($how, $ref, $i, $child = '') {
		global $WT_TREE, $DEBUG;

		$l_person = $this->allIndividuals[$ref];
		echo '<tr><td class="list_value_wrap" align="';
		echo ($child ? 'right' : 'left');
		echo '">' . $i . $child . '</td>';
		echo '<td class="list_value_wrap" align="center">' . (floor(log($i) / log(2)) + 1) . '</td>';
		if ($how == 'full') {
			$date = $l_person->getBirthDate();
			echo '<td class="list_value_wrap" align="left">' . $l_person->getFullName() . $l_person->getSexImage() . '</td><td class="list_value_wrap"></td>';
			if (!$child) {
				$rowspan = (isset($this->families[$i]) && count($this->children[$i]) > 1 ? (count($this->children[$i]) - 1) : 1);
				$nrChilds = (isset($this->families[$i]) && count($this->children[$i]) >= 1 ? max(count($this->children[$i]) - 1, 1) : '&nbsp;');
				echo '<td valign="middle" class="list_value_wrap" align="center" rowspan="' . $rowspan . '">' . $nrChilds . '</td>';
			}
			$wt_place = new Place($l_person->getBirthPlace(), $WT_TREE);
			$place_short = $wt_place->getShortName();
			echo '<td class="list_value_wrap" align="left">' . $this->getEventImg('birt', $l_person) . $date->Display() . '</td><td class="list_value_wrap" align="left">' . $place_short . '</td>' . "\n";
			echo '<td class="list_value_wrap" align="left">' . $this->parents_age($ref) . '</td>';
			if ($l_person->isDead()) {
				$date = $l_person->getDeathDate();
				$wt_place = new Place($l_person->getDeathPlace(), $WT_TREE);
				$place_short = $wt_place->getShortName();
				echo '<td class="list_value_wrap" align="left">' . $this->getEventImg('deat', $l_person) . $date->Display() . '</td><td class="list_value_wrap" align="left">' . $place_short . '</td>';
			} else {
				echo '<td></td><td></td>';
			}
		} else {
			echo '<td class="list_value_wrap">' . $l_person->getFullName() . $l_person->getSexImage() . '</td>';
			echo '<td class="list_value_wrap" align="left">' . $this->ancestorsRev[$ref] . '</td>' ;
			echo '<td class="list_value_wrap" align="left"></td>' ;
			echo '<td colspan="2" class="list_value_wrap" align="left">' . $this->getEventImg('birt', $l_person) . $l_person->getBirthYear() . '</td>';
			echo '<td colspan="1" class="list_value_wrap" align="center">' . $this->parents_age($ref) . '</td>';
			echo '<td colspan="2" class="list_value_wrap" align="left">' . $this->getEventImg('deat', $l_person) . $l_person->getDeathYear() . '</td>';
		}
		if ($DEBUG) {
			echo '<td>d=' . $DEBUG . '</td>';
			if ($DEBUG & 8) {
				if (isset($this->leaves[$i]) && $this->leaves[$i]) {
					echo "<td>No Parents $this->leaves[$i]/$this->genShow i=" . ($i * (2 << ($this->genShow-$this->leaves[$i])) / 2) . "</td>";
				} else {
					echo '<td></td>';
				}
			}
			if ($DEBUG & 128) {
				if (isset($this->children[$i]) && !$child) {
					$n =(count($this->children[$i]) - 1);
					echo '<td>' . ($this->children[$i][$n]) . '/' . $n . ' ; ';
					print_r($this->children[$i]);
					echo '</td>';
				} else {
					echo '<td></td>';
				}
			}
			if ($DEBUG & 16) {
				if (isset($this->positions[$i])) {
					echo '<td>p=' . $this->positions[$i] . '</td>' . "\n";
				} else {
					echo '<td align="right"><font color="red">no y</td>' . "\n";
				}
			}
			if ($DEBUG & 512) {
				echo '<td>';
				$facts = $l_person->getIndiFacts();
				foreach ($facts as $key => $item) {
					if ($item->getTag() == 'OCCU') {
						echo $item->getDetail();
					}
				}
				echo '</td>';
			}
		}
		echo "</tr>\n";
		return;
	}
	/**
	* Display info about used images of events
	*
	* @param string $output	type of output
	* @return nothing
	*/
	private function displayEventInfo ($output) {
		if ($output == 'PDF') {
			$html = $this->getEventImg('all', null);
			/* get number of lines the tcpdf function getNumLines works not for html text
			* here we can count the <br> tags
			*/
			$h = count(preg_split('/<br>/', $html)) - 1;
			if ($this->pageorient == 'portrait') {
				$x_pos = $this->xBrim + $this->xOffset;
				$y_pos = $this->yBrim + $this->headerHeight;
			} else {
				$x_pos = $this->xBrim;
				$y_pos = $this->yBrim + $this->yOffset + //$this->pageHeightAdd +
				($this->yWidth + $this->ySpace) * ($this->genShow) - 2.2 * $h; // XXX Why 2.2 times $h
			}
			$this->pdf->MultiCell($this->xWidth, $h, $html, 0, 'L', 0, 1, $x_pos, $y_pos, true, 0, true, false, $h);
		} else {
			echo $this->getEventImg('all', null) . '<br>';
			echo FunctionsDate::timestampToGedcomDate(mktime(0, 0, 0, date("m"), date("d"), date("Y")))->Display() . '<br>';
			echo '<p><a href="#" onclick="window.close();">', I18N::translate('close'), '</a></p>';
		}
	}
	/**
	* Close PDF file and create output file
	*/
	protected function closePdf () {
		// reset pointer to the last page
		$this->pdf->lastPage();
		//Close and output PDF document
		$this->pdf->Output($this->MainPid . '_' . $this->descendantGenShow . '-' . //$this->allIndividuals[$this->ancestors[1]] . '_' .
		       $this->genShow . '_gen.pdf', 'I');
	}
	/**
	* show page borders 
	* @param string $output 
	*/
	private function pageBorder($output) {
		if ($output == 'PDF') {
			$size = $this->pdf->getPageDimensions();
			$this->pdf->PolyLine(array($this->xBrim, $this->yBrim,
				 $size['wk'] - $this->xBrim, $this->yBrim,
				 $size['wk'] - $this->xBrim, $size['hk'] - $this->yBrim,
				 $this->xBrim, $size['hk'] - $this->yBrim,
				 $this->xBrim, $this->yBrim,
				 $this->xBrim, $this->yBrim + $this->headerHeight,
				 $size['wk'] - $this->xBrim, $this->yBrim + $this->headerHeight
				 ), 'D');
			return;
		}
	}
	/**
	* 
	*/
	function compressDescendants () {
		return;
	}
	/**
	* 
	*/
	function setDescendantPosition ($pid) {
		return;
	}
	/**
	*
	* @create a roman numeral from a number
	*
	* @param int $num
	*
	* @return string
	*
	*/
	function romanNumerals($num) {
		$n = intval($num);
		$res = '';
		/*** roman_numerals array  ***/
		$roman_numerals = array(
                'M'  => 1000,
                'CM' => 900,
                'D'  => 500,
                'CD' => 400,
                'C'  => 100,
                'XC' => 90,
                'L'  => 50,
                'XL' => 40,
                'X'  => 10,
                'IX' => 9,
                'V'  => 5,
                'IV' => 4,
                'I'  => 1);
		foreach ($roman_numerals as $roman => $number) {
			/*** divide to get  matches ***/
			$matches = intval($n / $number);
			/*** assign the roman char * $matches ***/
			$res .= str_repeat($roman, $matches);
			/*** substract from the number ***/
			$n = $n % $number;
		}
		/*** return the res ***/
		return $res;
	}
}