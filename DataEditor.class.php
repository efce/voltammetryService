<?php

if ( !defined('C_DataEditor') ) { //start of ISDEF

define('C_DataEditor',true);

class formError extends Exception { //throwable error

}
include 'editAnalyteForm.class.php';

class DataEditor {

	private $cPage, $toDraw, $plotArea, $plotExtra, $activeAreaId, $descriptionAreaId, $editAnalyte, $mode, $meta_id, $mes_id;

	function __construct($parent)
	{
		$this->cPage=$parent;
		$this->toDraw['Body'] = '';
		$this->toDraw['Style'] = '';
		$this->toDraw['Scripts'] = '';
		$this->activeAreaId = 'plotArea';
		$this->plotArea = 'thePlot';
		$this->plotExtra = 'plotExtra';
		$this->descriptionAreaId = 'plotDesc';
		$this->editAnalyte = array();
	}

	function __destruct()
	{

	}

	function Check()
	{
		$allowedModes = array( 'listData', 'editData', 'showData', 'processData', 'JSON' );
		$mode = 'listData';
		if (isset($_GET['mode']) && in_array($_GET['mode'],$allowedModes) ) {
			$mode = $_GET['mode'];
		}
		$this->mode = $mode;
		
		switch ($this->mode) {
		case 'listData':
			$this->prepareListPlots();
			return;

		case 'editData':
			if ( !isset($_GET['metaid']) || !is_numeric($_GET['metaid']) 
			|| !isset($_GET['mesid']) || !is_numeric($_GET['mesid']) ) {
				throw new Exception('Get method malformed.');
			}
			$this->meta_id = $_GET['metaid'];
			$this->mes_id = $_GET['mesid'];
			$this->cPage->dh->loadStruct($this->meta_id);
			if ( !$this->cPage->dh->isLoaded() ) {
				throw new Exception('Data not loaded.');
			}
			$theData =& $this->cPage->dh->getStruct();
			$num = 0;
			if ( isset($theData['Analytes']) && is_array($theData['Analytes']) ) {
				foreach( $theData['Analytes'] as $aid=>$aval ) {
					$this->editAnalyte[$num] = new editAnalyteForm($theData, $aid); // Edit analyte //
					$num++;
				}
			}
			$this->editAnalyte[$num] = new editAnalyteForm($theData, false); //Add new analyte //
			foreach ( $this->editAnalyte as &$ea ) {
				$ea->checkAndUpdate();
			}
			return;

		case 'processData':
			if ( !isset($_GET['metaid']) || !is_numeric($_GET['metaid']) 
			|| !isset($_GET['mesid']) || !is_numeric($_GET['mesid']) ) {
				throw new Exception('Get method malformed.');
			}
			$this->meta_id = $_GET['metaid'];
			$this->mes_id = $_GET['mesid'];
			$this->cPage->dh->loadStruct($this->meta_id);
			if ( !$this->cPage->dh->isLoaded() ) {
				throw new Exception('Data not loaded.');
			}
			$this->prepareDataProcess($this->cPage->dh->getStruct()); 
			return;

		case 'JSON': // for debugging, will be changed for header recognison //
			header('Content-Type: application/json');
			if ( !isset($_GET['processid']) 
			|| !is_numeric($_GET['processid']) 
			|| $_GET['processid'] == 0 
			|| !isset($_GET['mesid']) 
			|| !is_numeric($_GET['mesid']) ) {
				die('GET malformed.' . print_r($_GET,true) );
			}
			$acts = array('preview','final');
			if ( !isset($_GET['act']) || !in_array($_GET['act'], $acts) ) {
				die('Act not set in GET.');
			}

			$proc_method = $this->cPage->db->get(T_PROC_METHODS,'id=' . $_GET['processid']);
			if ( !isset($proc_method[0]['name']) ) {
				die('Unknown method.');
			}

			$this->cPage->dh->loadStruct(NULL,$_GET['mesid']);
			if (!defined($proc_method[0]['name']))	
				include './methods/' . $proc_method[0]['name'] . '.class.php';
			$proc = new $proc_method[0]['name']();
			switch($_GET['act']) {
			case 'preview':
				$proc->preparePreview($this->plotArea, $this->activeAreaId, $this->descriptionAreaId, $this->plotExtra);
				echo json_encode($proc->jsonReturn());
				exit;
			case 'final':
				$proc->prepareFinal($this->plotArea , $this->activeAreaId, $this->descriptionAreaId, $this->plotExtra);
				echo json_encode($proc->jsonReturn());
				exit;
			default:
				exit;
			}

		default:
			throw new Exception('Uknown mode.');

		}

	}

	function Draw()
	{
		$this->toDraw['Style'] .= ' 
.theTable { display: table; } 
.theRow { display: table-row } 
.theCell { display: table-cell; vertical-align: top; } 
#analyteDataForms { width: 245px; } 
';
		switch( $this->mode ) {
		case 'listData':
			$this->prepareDrawListPlots();
			break;

		case 'editData':
			$this->prepareDrawEditData();
			break;

		case 'processData':
			$this->prepareDrawDataProcess();
			break;

		case 'JSON': // for debugging, will be changed for header recognison //
			// JSON TU NIGDY NIE POWIEN DOJSC //
			throw new Exception('JSON went too far.');
		default:
			throw new Exception('This shouldn\'t happen.');
		}

		return $this->toDraw;
	}

	function prepareListPlots()
	{
	}
	
	function prepareDrawListPlots()
	{
		$uid = $this->cPage->sesMan->isLoggedAs();
		$user_plots_meta = false;
		$this->user_plots = '';
		if (is_numeric($uid)) {
			$public_plots_meta = $this->cPage->db->get(T_METADATA,'is_public=1 AND owner_id!=' . $uid . ' ORDER BY file_added DESC LIMIT 10');
			$user_plots_meta = $this->cPage->db->get(T_METADATA,'owner_id='.$uid . ' ORDER BY file_added DESC');
			$this->user_plots = '<table><tr><th>Upload date:</th><th>File name:</th><th>Actions</th></tr>';
			if (is_array($user_plots_meta)) {
				foreach( $user_plots_meta as $up ) {
					$mesid = $this->cPage->db->query('SELECT id FROM `' . T_MESDATA . '` WHERE meta_id=' . $up['id'] .' AND process_id=0', true);
					if ( !isset($mesid[0]['id']) ) {
						continue;
					}
					$this->user_plots .= '<tr>';
					$this->user_plots .= '<td>' . $up['file_added'] . '</td>';
					$this->user_plots .= '<td>' . $up['file_name'] . '</td>';
					$this->user_plots .= '<td>';
					//$this->user_plots .= '<form action="?name=manage&mode=showData&metaid=' . $up['id']  . '&mesid='.$mesid[0]['id'].'" method="POST"><input type="submit" name="' . $up['id'] .'" value="Show Data"></form>';
					$this->user_plots .= '<form action="?name=manage&mode=processData&metaid=' . $up['id']  . '&mesid='.$mesid[0]['id'].'" method="POST"><input type="submit" name="' . $up['id'] .'" value="Process Data"></form>';
					$this->user_plots .= '<form action="?name=manage&mode=editData&metaid=' . $up['id']  . '&mesid=' . $mesid[0]['id'].'" method="POST"><input type="submit" name="' . $up['id'] .'" value="Edit Data"></form>';
					$this->user_plots .= '</tr>';
					
				}
			}
			$this->user_plots .= '</table>';
		} else {
			$public_plots_meta = $this->cPage->db->get(T_METADATA,'is_public=1 ORDER BY file_added DESC LIMIT 10');
		}
		$this->public_plots = '';
		if ( is_array($public_plots_meta) ) {
			foreach( $public_plots_meta as $pp ) {
				$this->public_plots .= '<li class="pp"><a href="?name=manage&action=showData&metaid=' . $pp['id'] . '">' . $pp['file_added'] . ' - ' . $pp['file_name'] . '</a></li>';
			}
		}
		$this->toDraw['Body'] = '<div class="user_plots"><p>Lista danych wgranych przec Ciebie: <ul class="upl">';
		$this->toDraw['Body'] .= $this->user_plots;
		$this->toDraw['Body'] .= '</ul></div>';
		$this->toDraw['Body'] .= '<div class="public_plots"><p>Lista 10 ostatnich publicznych danych:</p><ul class="ppl">';
		$this->toDraw['Body'] .= $this->public_plots;
		$this->toDraw['Body'] .= '</ul></div>';
		$this->toDraw['Style'] .= '
.user_plots { border: 1px black solid; float: left; }
.public_plots { border: 1px black solid; float: right; }
';
	}

	function prepareDataProcess(&$theData)
	{
	}

	function prepareDrawDataProcess()
	{
		$theData =& $this->cPage->dh->getStruct();
		$analytes = array();
		if ( isset($theData['Analytes']) && is_array($theData['Analytes']) ) {
			foreach ( $theData['Analytes'] as $aid=>&$aval) {
				$analytes[$aid] = $this->getAnalyteDesc($aid, $aval);
				$anProc[$aid] = $this->getAnalyteProc($aid);
				$anCal[$aid] = $this->getAnalyteCal($aid);
			}
		}
		$plotProc = $this->getPlotProc();
		$plotCal = $this->getPlotCal();
		$plot = $this->getDrawPlot();
		$this->toDraw['Body'] .= '<div class="theTable">';
		$this->toDraw['Body'] .= '<div class="theRow">';
		$this->toDraw['Body'] .= '<div class="theCell">';
		$this->toDraw['Body'] .= $plot;
		$this->toDraw['Body'] .= '</div>';// end table cell //
		
		$this->toDraw['Body'] .= '<div class="theCell" id="analyteDataForms">';
		$keys = array_keys($analytes);
		$nrOfKeys = count($keys);
		for ( $i=0; $i<$nrOfKeys; ++$i) {
			$this->toDraw['Body'] .= '<div id="analyte' . $keys[$i] . '">';
			$this->toDraw['Body'] .= $analytes[$keys[$i]];

			$this->toDraw['Body'] .= '<div id="procPerAnalyte' . $keys[$i].'">';
			$this->toDraw['Body'] .= $anProc[$keys[$i]]['Body'];
			$this->toDraw['Body'] .= '</div>'; // END procPerAnalyte
			$this->toDraw['Body'] .= '<div id="calPerAnalyte' . $keys[$i].'">';
			$this->toDraw['Body'] .= $anCal[$keys[$i]]['Body'];
			$this->toDraw['Body'] .= '</div>'; // END procPerAnalyte

			$this->toDraw['Body'] .= '</div>'; // END analyte

			$this->toDraw['Style'] .= $anProc[$keys[$i]]['CSS'];
			$this->toDraw['Scripts'] .= $anProc[$keys[$i]]['Scripts'];
			$this->toDraw['Style'] .= $anCal[$keys[$i]]['CSS'];
			$this->toDraw['Scripts'] .= $anCal[$keys[$i]]['Scripts'];
		}
		$this->toDraw['Body'] .= '</div>' . /*table-cell*/ '</div>' /*table-row*/;
		$this->toDraw['Body'] .= '<div class="theRow">';
		$this->toDraw['Body'] .= '<div class="theCell">';
		$this->toDraw['Body'] .= '<div id="procPerPlot">';
		$this->toDraw['Body'] .= $plotProc['Body'];
		$this->toDraw['Body'] .= '</div>'; //END procPerPlot
		$this->toDraw['Body'] .= '<div id="calPerPlot">';
		$this->toDraw['Body'] .= $plotCal['Body'];
		$this->toDraw['Body'] .= '</div>'; //END calPerPlot
		$this->toDraw['Body'] .= '</div>'; //END theCell
		$this->toDraw['Body'] .= '</div>'; //END theRow
		$this->toDraw['Body'] .= '</div>'; //END theTable

		$this->toDraw['Style'] .= $plotProc['CSS'];
		$this->toDraw['Style'] .= $plotCal['CSS'];
		$this->toDraw['Scripts'] .= $plotProc['Scripts'];
		$this->toDraw['Scripts'] .= $plotCal['Scripts'];
	}

	private function getAnalyteCal($aid) // calibration methods used per analyte
	{
		$perAnal = $this->cPage->db->get(T_CAL_METHODS,'per_analyte=1');
		if ( !isset($perAnal[0]['name']) ) {
			return;
		}
		$r['Body'] = '';
		$r['Scripts'] = '';
		$r['CSS'] = '';
		foreach( $perAnal as $pa ) {
			$query_preview = $this->cPage->getCurrentURL() . '&analid='.$aid.'&calid=' . $pa['id'] .'&act=preview';
			$query_final   = $this->cPage->getCurrentURL() . '&analid='.$aid.'&calid=' . $pa['id'] . '&act=final';
			$r['Body'] .= '<button id="cal' . $pa['id'].'">'.$pa['button_name'].'</button>';
			if ( empty($pa['setters']) ) {
				$r['Scripts'] .= '$(\'#' . $pa['id'] . '\').click(function(e) { window.location("' . $query_final . '");};);';
			} else {
				$r['Scripts'] .= $this->generateJavascriptProcessing($this->activeAreaId, 'cal' . $pa['id'], json_decode($pa['setters']), $query_preview, $query_final);
			}
		}
		return $r;
	}

	private function getAnalyteProc($aid) // processing methods used per analyte
	{
		$perAnal = $this->cPage->db->get(T_PROC_METHODS,'per_analyte=1');
		if ( !isset($perAnal[0]['name']) ) {
			return;
		}
		$r['Body'] = '';
		$r['Scripts'] = '';
		$r['CSS'] = '';
		foreach( $perAnal as &$pa ) {
			$query_preview = $this->cPage->getCurrentURL() . '&analid='.$aid.'&procesid=' . $pa['id'] .'&act=preview';
			$query_final   = $this->cPage->getCurrentURL() . '&analid='.$aid.'&procesid=' . $pa['id'] . '&act=final';
			$r['Body'] .= '<button id="cal' . $pa['id'].'">'.$pa['button_name'].'</button>';
			if ( empty($pa['setters']) ) {
				$r['Scripts'] .= '$(\'#' . $pa['id'] . '\').click(function(e) { window.location("' . $this->cPage->getCurrentURL . '&process_id=' . $pa['id'] . '&act=final");};);';
			} else {
				$r['Scripts'] .= $this->generateJavascriptProcessing($this->activeAreaId, 'cal' . $pa['id'], json_decode($pa['setters']), $query_preview, $query_final);
			}
		}
		return $r;
	}

	private function getPlotProc() // processing methods used per plot
	{
		$perPlot = $this->cPage->db->get(T_PROC_METHODS,'per_analyte=0');
		if ( !isset($perPlot[0]['name']) ) {
			return;
		}
		$r['Body'] = '';
		$r['Scripts'] = '';
		$r['CSS'] = '';
		foreach( $perPlot as $pp ) {
			$query_preview = $this->cPage->getCurrentURL() . '&procesid=' . $pp['id'] .'&act=preview';
			$query_final   = $this->cPage->getCurrentURL() . '&procesid=' . $pp['id'] . '&act=final';
			$r['Body'] .= '<button id="cal' . $pp['id'].'">'.$pp['button_name'].'</button>';
			if ( empty($pp['setters']) ) {
				$r['Scripts'] .= '$(\'#' . $pp['id'] . '\').click(function(e) { window.location("'.$query_final.'");};);';
			} else {
				$r['Scripts'] .= $this->generateJavascriptProcessing($this->activeAreaId, 'cal' . $pp['id'], json_decode($pp['setters']), $query_preview, $query_final);
			}
		}

	}

	private function getPlotCal() // calibration methods used per plot
	{
		$perPlot = $this->cPage->db->get(T_CAL_METHODS,'per_analyte=0');
		if ( !isset($perPlot[0]['name']) ) {
			return;
		}
		$r['Body'] = '';
		$r['Scripts'] = '';
		$r['CSS'] = '';
		foreach( $perPlot as $pp ) {
			$query_preview = $this->cPage->getCurrentURL() . '&calid=' . $pp['id'] .'&act=preview';
			$query_final   = $this->cPage->getCurrentURL() . '&calid=' . $pp['id'] . '&act=final';
			$r['Body'] .= '<button id="cal' . $pp['id'].'">'.$pp['button_name'].'</button>';
			if ( empty($pp['setters']) ) {
				$r['Scripts'] .= '$(\'#' . $pp['id'] . '\').click(function(e) { window.location("'.$query_final.'");};);';
			} else {
				$r['Scripts'] .= $this->generateJavascriptProcessing($this->activeAreaId, 'cal' . $pp['id'], json_decode($pp['setters']), $query_preview, $query_final);
			}
		}
	}
	
	private function getAnalyteDesc($aid, $aval)
	{
		
	}

	private function getPlotProcessing()
	{
		$perPlot = $this->cPage->db->get(T_CAL_METHODS,'per_plot=1');
		if ( !isset($perPlot[0]['name']) ) {
			return;
		}
		foreach( $perPlot as $pp ) {
			$query_preview = '';
			$query_final = '';
			$r['Body'] = '<button id="cal' . $pp['id'].'">'.$pp['button_name'].'</button>';
			$r['Scripts'] = $this->generateJavascriptProcessing($this->activeAreaId, 'cal' . $pp['id'], json_decode($pp['setters']), $query_preview, $query_final);
		}
		return $r;
	}

	function prepareDrawEditData()
	{
		//$this->prepareDetailsForm(true,$onupload); //editable details /
		$plot = $this->getDrawPlot();
		$this->toDraw['Body'] .= '<div class="theTable">';
		$this->toDraw['Body'] .= '<div class="theRow">';
		$this->toDraw['Body'] .= '<div class="theCell">';
		$this->toDraw['Body'] .= $plot;
		$this->toDraw['Body'] .= '</div>';// end table cell //
		
		$this->toDraw['Body'] .= '<div class="theCell" id="analyteDataForms">';

		foreach($this->editAnalyte as $ea) {
			$dr = $ea->getDraw();
			$this->toDraw['Body'] .= $dr['Body'];
			$this->toDraw['Style'] .= $dr['CSS'];
			$this->toDraw['Scripts'] .= $dr['Scripts'];
		}
		$this->toDraw['Body'] .= '</div>'; // end #analyteDataForms (table-cell) //
		$this->toDraw['Body'] .= '</div>'; // end table-row //
		$this->toDraw['Body'] .= '</div>'; // end table //
		
		
	}

	function prepareDrawProcessData()
	{
		/*
		** TAKIE TAM DO ZMIANY
		*/
		$meta_id = $_GET['metaid'];
		//$theData =& $this->cPage->dh->getStruct(); //ograniczamy zuzycie pamieci
		if ( isset($_GET['mesid']) && is_numeric($_GET['mesid']) && isset($this->cPage->dh->getStruct()['Mesdata'][$_GET['mesid']]) )
			$mes_id = $_GET['mesid'];
		elseif ( isset($_GET['procid']) && is_numeric($_GET['procid']) && isset($theData['Mesdata']['Mesdata_proc_to_id'][$_GET['procid']]) )
			$mes_id = $theData['Mesdata']['Mesdata_proc_to_id'][$_GET['procid']];
		if (!isset($_GET['mesid']) || !isset($_GET['procid']) )
			$mes_id = $theData['Mesdata_proc_to_id'][0];

		/* dane przydatne */
		$xstart = $theData['Mesdata'][$mes_id]['Img_firstPointX'];
		$xend = 700;
		$ystart = 25;
		$yend = 125;
		$processid = 2;
		$analid = 60;
		$perPlot = $this->cPage->db->get(T_CAL_METHODS,'per_plot=1');
		if ( !isset($perPlot[0]['name']) ) {
			return;
		}
		foreach( $perPlot as $pp ) {
			$query_preview = '';
			$query_final = '';
			$this->toDraw['Body'] .= '<button id="cal' . $pp['id'].'">'.$pp['button_name'].'</button>';
			$this->toDraw['Scripts'] .= $this->generateJavascriptProcessing($this->activeAreaId, 'cal' . $pp['id'], json_decode($pp['setters']), $query_preview, $query_final);
		}
	}

	function generateJavascriptProcessing($imgid, $buttonid, $setters, $url1, $url2)
	{
$ret = '
					$(document).ready(function() {
						document.oncontextmenu = function() {return false;};
						$("html").mousedown(function(event) {
							if( event.which == "3" ){
								$(".toBeDisabled").attr("disabled",false);
							}
						});
  						$(\'#'.$buttonid.'\').click(function(e) {;
							e.preventDefault();
							$(".toBeDisabled").attr("disabled",true);

  							img_id=\''.$imgid.'\';
  							window.clicked = \'no\';';
$ret .= 						'var setters = {';
	for ($i=0;$i<count($setters);$i++) { 
		$ret .= 					$i.':{name:\''.$setters[$i]->name.'\',color:\''.$setters[$i]->color.'\'},';
	}
		$ret = substr($ret,0,-1);
$ret .= 							'};';
$ret .=      						'
							var offset = $(\'#\' + img_id).offset();
      							window.current=0;
							window.setters_positions = {};
      							window.color = setters[current].color;
      							window.name = setters[current].name;
      							var h = ($(\'#\' + img_id).height()) + \'px\';
      							var w = \'2px\';
      							$("body").append(
							   $(\'<div id="\' + window.name + \'"></div>\')
							       .css(\'position\', \'absolute\')
							       .css(\'top\', (offset.top) + \'px\')
							       .css(\'left\', (offset.left) + \'px\')
							       .css(\'width\', w)
							       .css(\'height\', h)
							       .css(\'background-color\', \'#\' + window.color)
							);
						        $(\'#\' + window.name).click(function(e) {
								e.preventDefault();
								create_final_setter(window.name,window.color,offset.left,offset.top,w,h);
							      });
							alert(img_id);
						        $(\'#\' + img_id).on(\'mousemove\', function(e){
							  if ( window.clicked == \'yes\' ) {
								window.current=window.current+1;
								if ( undefined == setters[window.current] ) {
									   $(\'#\' + img_id).unbind(\'mousemove\');
									   console.log(window.setters_positions);
									   $(".final_setter").css(\'display\',"none");
									   $.ajax({
										  url: "'.$url1.'",
										  type: "POST",
										  data: {setters : window.setters_positions},
										  success: function(data){
											$.each(data, function(key,val){
												eval(val);
											})
											$(\'#\' + img_id).click(function(){
												   $.ajax({
													  url: "'.$url2.'",
													  type: "POST",
													  data: {setters : window.setters_positions},
													  success: function(data){
														$.each(data, function(key,val){
															eval(val);
														})
												  	  }
											   	   });
												   $(\'#\' + img_id).unbind(\'click\');
											});
											$("html").mousedown(function(event) {
												if( event.which == "3" ){
													window.goBack();
													$(".final_setter").css(\'display\',"block");
												   	$(\'#\' + img_id).unbind(\'click\');
												}
											});										
										  }
									   });
								} else {
								   window.name=setters[window.current].name;
								   window.color=setters[window.current].color;
								   window.clicked = \'no\';
								   create_setter(window.name,window.color,offset.left,offset.top,w,h);
								   $(\'#\' + window.name).click(function(e) {
									create_final_setter(window.name,window.color,offset.left,offset.top,w,h);
								   });
								}
							} else {
								$(\'#\' + window.name).css({
								   left:  e.pageX,
								   position: \'absolute\'
								});
							}
						      });
						  });
						});
						function create_setter(name,color,off_left,off_top,w,h) {
							$("body").append(
								$(\'<div id="\' + name + \'"></div>\')
								    .css(\'position\', \'absolute\')
								    .css(\'top\', (off_top) + \'px\')
								    .css(\'left\', (off_left) + \'px\')
								    .css(\'width\', w)
								    .css(\'height\', h)
								    .css(\'background-color\', \'#\' + color)
							);
						}
						function create_final_setter(name,color,off_left,off_top,w,h) {
						  d_offset= $(\'#\' + name).offset();
						  $(\'#\' + name).remove();
						  window.setters_positions[window.current]={ name: window.name, position: d_offset.left - off_left }
						  $("body").append(
						     $(\'<div id="\' + name + \'_final" class="final_setter"></div>\')
						       .css(\'position\', \'absolute\')
						       .css(\'top\', (d_offset.top) + \'px\')
						       .css(\'left\', (d_offset.left) + \'px\')
						       .css(\'width\', w)
						       .css(\'height\', h)
						       .css(\'background-color\', \'#\' + color)
						  );
						  window.clicked = \'yes\';
						}
';
		return $ret;
	}

	function getDrawPlot()
	{

		$theData =& $this->cPage->dh->getStruct();
		$uid = $this->cPage->sesMan->isLoggedAs();
		if ( $theData['Meta_owner'] !=$uid ) {
			throw new Exception('Not permited.');
		}

		include 'plotMaker.class.php';
		$plotmaker = new plotMaker($this);//?
		$plotmaker->RegularPlot($this->mes_id);
		if ( !isset($theData['Mesdata'][$this->mes_id]['Img_firstPointX']) ) {
			$this->cPage->dh->loadStruct(NULL,$this->mes_id);
		}
		$areaW = PLOT_WIDTH - $theData['Mesdata'][$this->mes_id]['Img_firstPointX'] - LEGEND_WIDTH;
		$areaH = PLOT_HEIGHT - 25 /*top margin*/ - 45 /*bottom margin*/;
		$ret = '<div style="background-image: url(' . $this->cPage->dh->getPlotURL($this->mes_id)  . ');width: ' . PLOT_WIDTH . 'px;height: ' . PLOT_HEIGHT . 'px; position: relative; display: block;" id="'.$this->plotArea.'"><div id="'.$this->activeAreaId.'" style="position:relative; width:'.$areaW.'px;height:'.$areaH.'px;left:'.$theData['Mesdata'][$this->mes_id]['Img_firstPointX'].'px;top:25px"></div></div>';
		$ret .= '<div id="'.$this->descriptionAreaId.'"></div><div id="'.$this->plotExtra.'"></div>';
		return $ret;
	}
}

} // end od ISDEF
