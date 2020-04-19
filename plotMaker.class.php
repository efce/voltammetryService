<?php

if ( !defined('C_plotMaker') ) { // start of IFDEF

define('C_plotMaker', true);

function getCoords($img, $mesdata_id, $shape, $row, $col, $x1, $y1) {
	$cPage = mainPage::getInstance();
	if ( $row == 0 && $col == 0 ) {
		$cPage->dh->loadStruct(NULL,$mesdata_id);
		$theData =& $cPage->dh->getStruct();
		$theData['Mesdata'][$mesdata_id]['Img_firstPointX'] = $x1;
		$theData['Mesdata'][$mesdata_id]['Img_firstPointY'] = $y1;	
		$theData['Mesdata'][$mesdata_id]['Status'] = 'updated';
		$cPage->dh->updateDBMesdata(false);
	}
}

class plotMaker {

	protected $rawData, $cPage, $pid, $ymin, $ymax, $plotname, $alreadyUpToDate, $img_filename, $title, $process_method, $firstCoord;

	function __construct() {
		$this->cPage = mainPage::getInstance();
	}

	function __destruct() {
	}

	function listPlots() 
	{
		$tmp = $this->cPage->db->get(T_MESDATA,'id>0 ORDER BY id DESC');
		$ret= '<p>Available plots:<br>';
		foreach ($tmp as $t) {
			$ret.= '<a href="?name=viewplot&plotId='.$t['id'].'">id:' . $t['id'].'</a><br>';
		}
		$ret.='</p>';
		return $ret;
	}

	function RegularPlot($mes_id)
	{
		if ( !$this->cPage->dh->isLoaded() || !$this->cPage->dh->isLoadedMes($mes_id) )
			$this->cPage->dh->loadStruct(NULL,$mes_id);

		$theData = $this->cPage->dh->getStruct();
		$this->process_method = 'none';
		$this->title = 'Data from file: '. $theData['File_name'] . ' processed with: ' . $this->process_method;
		// TODO: to co ponizej zaczete !
		if ( isset($theData['Mesdata'][$mes_id]['Img_filename']) && $theData['Mesdata'][$mes_id]['Img_filename'] != NULL )
			$this->img_filename = $theData['Mesdata'][$mes_id]['Img_filename'];
		if ( $theData['Mesdata'][$mes_id]['Img_updated'] == 1 && is_file($this->img_filename) ) {
			$this->alreadyUpToDate = true;
			return;
		} elseif ( $theData['Mesdata'][$mes_id]['Img_updated'] == false && is_file($this->img_filename)) {
			unlink($this->img_filename);
			$this->img_filename = $this->generateNewFilename();
		} else {
			$this->img_filename = $this->generateNewFilename();
		}
		$this->cPage->dh->loadDataOfMes($mes_id);
		$theData =& $this->cPage->dh->getStruct();
		$this->ymin = NULL;
		$this->ymax = NULL;
		//die('XXXXX:' . print_r($theData,true));
		foreach ( $theData['Mesdata'][$mes_id]['Data']['Y'] as &$py ) {
			if ( $this->ymax == NULL ) 
				$this->ymax = max($py);
			if ( $this->ymin == NULL )
				$this->ymin = min($py);
			if ( $this->ymin > min($py) )
				$this->ymin = min($py);
			if ( $this->ymax < max($py) )
				$this->ymax = max($py);
		}
		include 'phplot/phplot.php';
		for ( $i=0;$i<(count($theData['Mesdata'][$mes_id]['Data']['Y'])+2);$i++ )
			$edata[$i] = '';
		$num = 0;
		$cnt = count($theData['Mesdata'][$mes_id]['Data']['Y']);
		$data = array_fill(0, ($cnt * count($theData['Mesdata'][$mes_id]['Data']['Y'][0])), $edata);
		$num = 0;
		$minX = min($theData['Mesdata'][$mes_id]['Data']['X'][0]);
		$maxX = max($theData['Mesdata'][$mes_id]['Data']['X'][0]);
		$minY = NULL;
		$maxY = NULL;
		$cnt = count($theData['Mesdata'][$mes_id]['Data']['Y']);

		for ( $i=0;$i<$cnt;++$i ) { 
			$lineStyle[$i] = 'solid';
			if (isset($theData['Mesdata'][$mes_id]['Data']['Names'][$i]) && !empty($theData['Mesdata'][$mes_id]['Data']['Names'][$i]) )
				$leg[$i] = '#' . ($i+1) . ': ' . $theData['Mesdata'][$mes_id]['Data']['Names'][$i];
			else
				$leg[$i] = '#' .($i+1);
                        $p = 0;
			if ( $minY === NULL ) {
				$minY = min($theData['Mesdata'][$mes_id]['Data']['Y'][$i]);
				$maxY = max($theData['Mesdata'][$mes_id]['Data']['Y'][$i]);
			} 
			$newMinY = min($theData['Mesdata'][$mes_id]['Data']['Y'][$i]);
			if ( $minY > $newMinY )  {
                                $minY = $newMinY;
                        }
			$newMaxY = max($theData['Mesdata'][$mes_id]['Data']['Y'][$i]);
                        if ( $maxY < $newMaxY ) {
                                $maxY = $newMaxY;
                        }
			
                        while (isset($theData['Mesdata'][$mes_id]['Data']['Y'][$i][$p])) {
				$data[$num][1] = $theData['Mesdata'][$mes_id]['Data']['X'][0][$p];
				$data[$num][$i+2] = $theData['Mesdata'][$mes_id]['Data']['Y'][$i][$p];	
				$p++;
				$num++;
			}
		}
		$incY = ($maxY - $minY) * 0.02;
		$maxX = $this->cPage->signifNumbers($maxX);
		$minX = $this->cPage->signifNumbers($minX);
		$maxY = $this->cPage->signifNumbers( ($maxY + $incY) );
		$minY = $this->cPage->signifNumbers( ($minY - $incY) );
		$plot = new PHPlot(PLOT_WIDTH,PLOT_HEIGHT);
		$plot->SetTTFPath('./fonts');
		$plot->SetLineStyles($lineStyle);
		$plot->SetLineWidths(2);
		$plot->SetPointSizes(0);
		$plot->SetFontTTF('x_title', 'LiberationSans-Regular.ttf', 12);
		$plot->SetFontTTF('y_title', 'LiberationSans-Regular.ttf', 12);
		$plot->SetFontTTF('title', 'LiberationSans-Regular.ttf', 13);
		$plot->SetFontTTF('y_label', 'LiberationSans-Regular.ttf', 10);
		$plot->SetFontTTF('x_label', 'LiberationSans-Regular.ttf', 10);
		$plot->SetFontTTF('legend', 'LiberationSans-Regular.ttf', 10);
		$plot->SetXTitle('Potential / mV');
		$plot->SetYTitle("Current / \xC2\xB5A");
		$plot->SetTitle($this->title);
		$plot->SetImageBorderType('plain');
		$plot->SetPlotType('linepoints');
		$plot->SetDataType('data-data');
		$plot->SetDataValues($data);
		$plot->SetXDataLabelPos('none');
		$plot->SetXLabelType('data');
		$plot->SetMarginsPixels(NULL,LEGEND_WIDTH,NULL,NULL,NULL);
		$plot->SetLegendPosition(0, 0, 'image', 0, 0, 710, 26);
		$plot->SetLegend($leg);
		$plot->SetPlotAreaWorld($minX,$minY,$maxX,$maxY);
		$plot->SetIsInline(TRUE);
		$plot->SetCallback('data_points', 'getCoords', $mes_id);
		$plot->SetOutputFile($this->img_filename);
		$plot->DrawGraph();
		/** Struct sie zmienia przy draw graph **/
		$this->cPage->dh->loadStruct(NULL,$mes_id);
		$theData = $this->cPage->dh->getStruct();

		$theData['Mesdata'][$mes_id]['Img_filename'] = $this->img_filename;
		$theData['Mesdata'][$mes_id]['Img_updated'] = 1;
		$theData['Mesdata'][$mes_id]['Status'] = 'updated';
		$this->cPage->dh->updateDBMesdata(false);
	}

	function CalibrationPlot( $mes_id, $cal_id )
	{
		if ( !$this->cPage->dh->isLoadedCal($mes_id, $cal_id) )
			$this->cPage->dh->LoadStruct(NULL,$mes_id);
			if ( !$this->cPage->dh->isLoadedCal($mes_id, $cal_id) )
				throw new Exception('Database error.');

		$theData =& $this->cPage->dh->getStruct();
		$Xarr=$this->cPage->dh->getAnalyteConc($mes_id, $cal_id);
		$this->process_method = $this->cPage->dh->getCalibrationName($mes_id, $cal_id);
		$this->title = 'Data from file: '. $theData['File_name'] . ', calibration with: ' . $this->process_method;
		// TODO: to co ponizej zaczete !
		if ( isset($theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Img_filename']) 
		&& $theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Img_filename'] != NULL ) {
			$this->img_filename = $theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Img_filename'];
		}
		if ( $theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Img_updated'] == 1 
		&& is_file($this->img_filename) ) {
			$this->alreadyUpToDate = true;
			return;
		} elseif ( $theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Img_updated'] == false && is_file($this->img_filename)) {
			unlink($this->img_filename);
			$this->img_filename = $this->generateNewFilename();
		} else {
			$this->img_filename = $this->generateNewFilename();
		}
		$this->ymin = NULL;
		$this->ymax = NULL;
		//die(print_r($theData,true));
		foreach ( $theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Data']['Y'] as &$py ) {
			if ( $this->ymax == NULL ) 
				$this->ymax = max($py);
			if ( $this->ymin == NULL )
				$this->ymin = min($py);
			if ( $this->ymin > min($py) )
				$this->ymin = min($py);
			if ( $this->ymax < max($py) )
				$this->ymax = max($py);
		}
		include 'phplot/phplot.php';
		for ( $i=0;$i<(2*count($theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Data']['Y'])+2);$i++ )
			$edata[$i] = '';
		$num = 0;
		$nr_of_datapoints = count($theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Data']['Y'][0]) + (2*count($theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Data']['Y']));
		$data = array_fill(0,$nr_of_datapoints,$edata);
		$num = 0;
		$minX = NULL;
		$minY = NULL;
		$maxX = NULL;
		$maxY = NULL;
		$Xind = array();
		$i=0;
		foreach ( $Xarr as $k=>&$v) {
			$Xind[$i] = $k;
			$i++;
		}

		$cnt = count($theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Data']['Y']);

		for ( $i=0;$i<$cnt;$i++ ) { 
			$lineStyle[$i] = 'none';
			$pointSize[$i] = '8';
			if (isset($Xarr[$Xind[$i]]['Name']) && !empty($Xarr[$Xind[$i]]['Name']) )
				$leg[$i] = $Xarr[$Xind[$i]]['Name'];
			else
				$leg[$i] = 'NOT SET';
                        $p = 0;
			if ( $minX == NULL ) {
				$minX = min($theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Data']['X'][$i]);
				$maxX = max($theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Data']['X'][$i]);
				$minY = min($theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Data']['Y'][$i]);
				$maxY = max($theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Data']['Y'][$i]);
			} 
			if ( $minX > min($theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Data']['X'][$i]) )  {
				$minX = min($theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Data']['X'][$i]);
			}
			if ( $maxX < max($theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Data']['X'][$i]) )  {
                                $maX = max($theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Data']['X'][$i]);
			}
			if ( $minY > min($theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Data']['Y'][$i]) )  {
                                $minY = min($theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Data']['Y'][$i]);
                        }
                        if ( $maxY < max($theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Data']['Y'][$i]) )  {
                                $maxY = max($theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Data']['Y'][$i]);
                        }
			
                        while (isset($theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Data']['Y'][$i][$p])) {
				$data[$num][1] = $theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Data']['X'][$i][$p];
				$data[$num][$i+2] = $theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Data']['Y'][$i][$p];	
				$p++;
				$num++;
			}
		}
		for (  $i=0;$i<$cnt;$i++ ) {
			$lineStyle[$i+$cnt] = 'solid';
			$pointSize[$i+$cnt] = '0';
			$f_y_min = $this->cPage->dh->evalEq($theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Equation'],$minX);
			$f_y_max = $this->cPage->dh->evalEq($theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Equation'],$maxX);
			$data[$num][1] = $minX;
			$data[$num][$cnt+$i+2]=$f_y_min;
			$num++;
			$data[$num][1] = $maxX;
			$data[$num][$cnt+$i+2]=$f_y_max;
			$num++;
			$leg[] = $this->cPage->dh->dispEq($theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Equation']);
		}
		//print_r($data);
		$incY = ($maxY - $minY) * 0.02;
		$maxX = $this->cPage->signifNumbers($maxX);
		$minX = $this->cPage->signifNumbers($minX);
		$maxY = $this->cPage->signifNumbers($maxY + $incY);
		$minY = $this->cPage->signifNumbers($minY - $incY);
		$plot = new PHPlot(PLOT_WIDTH,PLOT_HEIGHT);
		$plot->SetTTFPath('./fonts');
		$plot->SetLineStyles($lineStyle);
		$plot->SetLineWidths(2);
		$plot->SetPointSizes($pointSize);
		$plot->SetFontTTF('x_title', 'LiberationSans-Regular.ttf', 12);
		$plot->SetFontTTF('y_title', 'LiberationSans-Regular.ttf', 12);
		$plot->SetFontTTF('title', 'LiberationSans-Regular.ttf', 13);
		$plot->SetFontTTF('y_label', 'LiberationSans-Regular.ttf', 10);
		$plot->SetFontTTF('x_label', 'LiberationSans-Regular.ttf', 10);
		$plot->SetFontTTF('legend', 'LiberationSans-Regular.ttf', 10);
		$plot->SetXTitle('Concentration / ?');
		$plot->SetYTitle("Current / \xC2\xB5A");
		$plot->SetTitle($this->title);
		$plot->SetImageBorderType('plain');
		$plot->SetPlotType('linepoints');
		$plot->SetDataType('data-data');
		$plot->SetDataValues($data);
		$plot->SetXDataLabelPos('none');
		$plot->SetXLabelType('data');
		$plot->SetLegend($leg);
		$legSize = $plot->GetLegendSize();
		$plot->SetLegendPosition(0, 0, 'image', 0, 0, 50, 26);
		$plot->SetMarginsPixels(NULL,LEGEND_WIDTH,(26+$legSize[1]+5),NULL,NULL);
		$plot->SetPlotAreaWorld($minX,$minY,$maxX,$maxY);
		$plot->SetIsInline(TRUE);
		$plot->SetOutputFile($this->img_filename);
		$plot->DrawGraph();
		$theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Img_filename'] = $this->img_filename;
		$theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Img_updated'] = 1;
		$theData['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Status'] = 'updated';
		$this->cPage->dh->updateDBCaldata($mes_id);
	}

	function Draw() {
		return '<img src="'.$this->img_filename.'">' . $this->listPlots() ;
	}

	function getPid() {
		return $this->pid;
	}

	function GetImgFilename()
	{
		return $this->img_filename;
	}
	
	function setFirstCoord($x,$y)
	{
		$this->firstCoord = array('x'=>$x, 'y'=>$y);
	}

	function generateNewFilename()
	{
		while ( true ) {
			$fn =  $this->cPage->plotDir . '/' . $this->generateString(12) . '.png';
			$isindb = $this->cPage->db->get(T_MESDATA,'img_filename="' . $fn . '"');
			if ( !isset($isindb[0]['img_filename']) && !is_file($fn) )
				return $fn;
		}
	}

	function generateString($len)
	{
		$chars = array('a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','r','s','t','u','w','x','y','z');
		$ret = '';
		for ($i=0;$i<$len;$i++)
			$ret .= $chars[rand(0,23)];
		return $ret;
	}
}

} // end of IFDEF
?>
