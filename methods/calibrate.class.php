<?php
if ( !defined('C_calibrate') ) { //start of IFDEF

define('C_calibrate', true);

class calibrate {

	private $ret, $parent;
	function __construct()
	{
		$this->ret = array();
	}


	function __destruct()
	{

	}

	function preparePreview($plotId, $areaId, $descId)
	{
		global $cPage;
		if ( isset( $_GET['mesid']) && is_numeric($_GET['mesid']) 
		&& isset($_GET['analid']) && is_numeric($_GET['analid']) ) {
			$backurl = $cPage->dh->getShowPlotPath($_GET['mesid']);
			$cPage->dh->loadStruct(NULL, $_GET['mesid']);
			$theData =& $cPage->dh->getStruct();
			if ( !isset($theData['Analytes'][$_GET['analid']]) ) {
				die('Wrong analid');
			}
			$plotX['start'] = $theData['Mesdata'][$_GET['mesid']]['Img_firstPointX'];
			$plotX['end'] = PLOT_WIDTH - LEGEND_WIDTH;
			$pixelSpan = $plotX['end'] - $plotX['start'];
		} else {
			die('not set');
		}
		if ( !isset($_POST['setters']) )
			die('AaaaAAaaAaaa');
		$setters = $_POST['setters'];
		$cPage->dh->loadDataOfMes($_GET['mesid']);
		//$theData = $cPage->dh->getStruct();
		if ( isset($setters[0]['position']) && isset($setters[1]['position'])) {
			foreach ( $setters as &$set ) {
				$positionPercent = $set['position'] / $pixelSpan;
				$nrofel = $theData['Mesdata'][$_GET['mesid']]['nr_of_points'];
				$set['datapoint'] = round($nrofel*$positionPercent);
				if ( $set['datapoint'] >= $theData['Mesdata'][$_GET['mesid']]['nr_of_points'] )
					$set['datapoint'] = $theData['Mesdata'][$_GET['mesid']]['nr_of_points'] -1;
				//$this->ret[] = 'alert("setter: ' . $set['name'] . ' jest na pozycji w ' . ($positionPercent*100)  .'%: ' . $set['position'] .', przy numerze punktu: ' . $pointInData . ' czyli pradzie: ' . $theData['Mesdata'][$_GET['mesid']]['Data']['Y'][0][$pointInData]  . '");';
			}
			if ( $setters[0]['datapoint'] > $setters[1]['datapoint'] ) {
				$calstart = $setters[1]['datapoint'];
				$calstop = $setters[0]['datapoint'];
			} else {
				$calstart = $setters[0]['datapoint'];
				$calstop = $setters[1]['datapoint'];
			}
			$min = array();
			$max = array();
			$txt = '';
			foreach ( $theData['Mesdata'][$_GET['mesid']]['Data']->Y as $key=>$singlePlot ) {
				$min[$key] = NULL;
				$max[$key] = NULL;
				for ($i=$calstart; $i<=$calstop; $i++) {
					if ( $min[$key] == NULL ) {
						$max[$key] = $singlePlot[$i];
						$min[$key] = $singlePlot[$i];
						continue;
					}
					if ( $singlePlot[$i] > $max[$key] ) {
						$max[$key] = $singlePlot[$i];
					} elseif ( $singlePlot[$i] < $min[$key] ) {
						$min[$key] = $singlePlot[$i];
					}
				}
				$calVal[$key] = $max[$key] - $min[$key];
				$txt .= $calVal[$key] . ',';
			}
			$Caldata['X'] = $theData['Analytes'][$_GET['analid']]['Conc'];
			$Caldata['Y'] = $calVal;
			$Caldata['Name'] = $theData['Analytes'][$_GET['analid']]['Name'];
			$Caldata['Units'] = $theData['Analytes'][$_GET['analid']]['Disp_units_id'];
			require_once('Math/math.php');
			$data = zc_math::matrix_transpose( array( 0=>$Caldata['X'], 1=>$Caldata['Y']) );
			$linear = new zc_math_linear($data);
			$linear->calculate();
			// y = k*x + m
			$slope=$linear->k;
			$intercept = $linear->m;
			$r = $this->linearCorrelation($Caldata['X'],$Caldata['Y']);
			$st_add_res = (-1*($intercept/$slope));
			$Caldata['Cal_method_id'] = $_GET['processid'];
			$Caldata['Analid'] = $_GET['analid'];
			$Caldata['Equation'] = array( '_type' => 'y=bx+a', 'b'=> $cPage->signifNumbers($slope), 'a' => $cPage->signifNumbers($intercept), 'r'=> $cPage->signifNumbers($r) );
			$Caldata['StandardAdditionResult'] =  $st_add_res;
			$Caldata['Data']['Y'][0] = $Caldata['Y'];
			unset($Caldata['Y']);
			$Caldata['Data']['X'][0] = $Caldata['X'];
			unset($Caldata['X']);
			$Caldata['Status'] = 'new';
			$Caldata['Img_filename'] = '';
			$Caldata['Img_updated'] = 0;
			$theData['Mesdata'][$_GET['mesid']]['Calibrations'][] =& $Caldata;
			$ids=$cPage->dh->updateDBCaldata($_GET['mesid']);

			if ( isset($ids[0]) && is_numeric($ids[0])) {
				include 'plotMaker.class.php';
				$pm = new plotMaker();
				$pm->CalibrationPlot($_GET['mesid'],$ids[0]);
				$this->ret[] = '$("#'.$plotId.'").css(\'background-image\', \'url("'.$cPage->dh->getShowCalPlotPath($_GET['mesid'],$ids[0]).'")\');';
			} else {
				die(json_encode( array(0=>'alert("Error while processing, please try again.")')));
			}
		}
		//$this->ret[] = '$("#'.$plotId.'").css(\'background-image\', \'url("http://pbs.twimg.com/profile_images/579645975029014529/g9UKBVRj.jpg")\');';
		$this->ret[] = 'window.goBack = function() {$("#'.$plotId.'").css(\'background-image\', \'url("'.$backurl.'")\');};';
	}

	function linearCorrelation( $x, $y )
	{
		$countx = count($x);
		$county = count($y);
		if ( $countx != $county )
			throw new Exception('error!!!');
		$meanx = array_sum($x) / count($x);
		$meany = array_sum($y) / count($y);
		$top = 0;
		for ($i=0;$i<$countx;$i++) {
			$top += ($x[$i]-$meanx)*($y[$i]-$meany);
		}
		$bottomx = 0;
		$bottomy = 0;
		for ($i=0;$i<$countx;$i++) {
			$bottomx += pow(($x[$i]-$meanx),2);
			$bottomy += pow(($y[$i]-$meany),2);
		}
		$bottom = sqrt($bottomx*$bottomy);
		return ($top/$bottom);
	}

	function addPerAnalyte()
	{
		return true;	
	}

	function addPerPlot()
	{
		return false;
	}

	function prepareFinal($plotId, $areaId, $descId, $extraId)
	{
		$this->ret[] = '$("#'.$extraId.'").css(\'background-image\', \'url("http://www.rybobranie.pl/pliki/encyklopedia/171/okon.jpg")\');';
		$this->ret[] = '$("#'.$extraId.'").css(\'width\', \''.PLOT_WIDTH.'\');';
		$this->ret[] = '$("#'.$extraId.'").css(\'height\', \''.PLOT_HEIGHT.'\');';
		$this->ret[] = '$("#'.$extraId.'").css(\'position\', \'absolute\');';
		$this->ret[] = '$("#'.$extraId.'").css(\'top\', \'200px\');';
		$this->ret[] = '$("#'.$extraId.'").css(\'left\', \'200px\');';
		$this->ret[] = 'window.goBack = function() {};';
	}

	function jsonReturn()
	{
		return $this->ret;
	}
}

} //end of IFDEF
