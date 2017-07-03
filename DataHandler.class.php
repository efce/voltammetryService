<?php

class DataHandler {

	private $action, $cPage;
	protected $dataStruct;
	private static $thisInstance = null;

	private function __construct()
	{
		$this->dataStruct = array();
		$this->cPage =& mainPage::getInstance();
	}

	public static function spawnInstance()
	{
		if ( static::$thisInstance == null ) {
			static::$thisInstance = new DataHandler();
		}
	}

	public static function &getInstance()
	{
		return static::$thisInstance;
	}

	function __destruct()
	{

	}

	function Check()
	{
		if (isset($_GET['mode']) && $_GET['mode'] == 'showPlot' && isset($_GET['msid']) && is_numeric($_GET['msid'])) {
			$this->loadStruct(NULL,$_GET['msid']);
			if ( isset($_GET['clid']) )
				$this->sendCalPNG($_GET['msid'],$_GET['clid']);
			else
				$this->sendMesPNG($_GET['msid']);
		} else {
			die();
		}
	}

	function getEditDataURL($meta_id, $mes_id)
	{
		if (is_numeric($meta_id) && is_numeric($mes_id) ) {
                        return '?name=manage&mode=editData&metaid='.$meta_id.'&mesid=' . $mes_id;
		} else {
			throw new Exception('Non numeric id');
		}
	}
	
	function getPlotURL($mes_id)
	{
		if (is_numeric($mes_id))
			return '?name=data&mode=showPlot&msid='.$mes_id;
		else
			throw new Exception('Non numeric id');
	}

	function getShowCalPlotURL($mes_id,$cal_id)
	{
		if (is_numeric($mes_id)&&is_numeric($cal_id))
			return '?name=data&mode=showPlot&msid='.$mes_id.'&clid='.$cal_id;
		else
			throw new Exception('Non numeric id');
	}

	function sendMesPNG($mes_id) 
	{
		if( !class_exists('plotMaker') )
			include 'plotMaker.class.php';
		$plotmaker = new plotMaker($this);//?
		$plotmaker->RegularPlot($mes_id);
		$file = $this->dataStruct['Mesdata'][$mes_id]['Img_filename'];
		$type = 'image/png';
		header('Content-Type:'.$type);
		header('Content-Length: ' . filesize($file));
		readfile($file);
		exit;
	}

	function sendCalPNG($mes_id,$cal_id) 
	{
		if( !class_exists('plotMaker') )
			include 'plotMaker.class.php';
		$plotmaker = new plotMaker($this);//?
		$plotmaker->CalibrationPlot($mes_id,$cal_id);
		$file = $this->dataStruct['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Img_filename'];
		$type = 'image/png';
		header('Content-Type:'.$type);
		header('Content-Length: ' . filesize($file));
		readfile($file);
		exit;
	}

	function loadStruct($meta_id=NULL,$mes_id=NULL)
	{
		if ( is_numeric($mes_id) && $meta_id==NULL )
			$loadby='mes_id';
		elseif ( is_numeric($meta_id) && $mes_id==NULL ) 
			$loadby='meta_id';
		else
			throw new Exception('Only one id can be numeric.');

		$mesNoDataQuery = 'SELECT `id`,`meta_id`,`process_id`,`nr_of_curves`,`nr_of_points`,`img_filename`,`img_updated`,`img_firstPointX`,`img_firstPointY` FROM `' .T_MESDATA . '` WHERE ';
		switch ($loadby) {
		case 'mes_id':
			$mesdata = $this->cPage->db->query($mesNoDataQuery . 'id=' . $mes_id, true);
			if ( !isset($mesdata[0]) )
				return false;
			$metadata = $this->cPage->db->get(T_METADATA,'id=' . $mesdata[0]['meta_id']);
			if ( !isset($metadata[0]) )
				throw new Exception('Data incomplete.');		
			$mesdata = $this->cPage->db->get(T_MESDATA,'meta_id=' . $metadata[0]['id']);
			if ( !isset($mesdata[0]['id']) )
				throw new Exception('Data corrupted.');
			break;
		case 'meta_id':
			$metadata = $this->cPage->db->get(T_METADATA,'id=' . $meta_id);
			if ( !isset($metadata[0]) )
				throw new Exception('Data incomplete.');		
			$mesdata = $this->cPage->db->query($mesNoDataQuery . 'meta_id=' . $metadata[0]['id'], true);
			if ( !isset($mesdata[0]['id']) )
				throw new Exception('Data corrupted.');
			break;
		}
		
		$uid = $this->cPage->sesMan->isLoggedAs();
		if ( !$metadata[0]['is_public'] && (!is_numeric($uid) || $uid != $metadata[0]['owner_id']) ) {
			throw new Exception('Not permitted.');
		}

		foreach( $mesdata as $md ) {
			$caldata = '';
			$mes_struct[$md['id']] = array(
				'Data' => new stdClass(), 
				'nr_of_curves' => $md['nr_of_curves'],
				'nr_of_points' => $md['nr_of_points'],
				'Img_filename' => $md['img_filename'],
				'Img_updated' => $md['img_updated'],
				'Img_firstPointX' => $md['img_firstPointX'],
				'Img_firstPointY' => $md['img_firstPointY'],
				'Process_id' => $md['process_id'],
				'Status' => 'old'
				);
			$proc_id_to_id[$md['process_id']]=$md['id'];
			$caldata = $this->cPage->db->get(T_CALDATA, 'mes_id=' . $md['id']);
			if ( isset($caldata[0]['data']) ) {
				foreach ( $caldata as $cd ) {
					$mes_struct[$md['id']]['Calibrations'][$cd['id']]['Cal_method_id'] = $cd['cal_meth_id'];
					$mes_struct[$md['id']]['Calibrations'][$cd['id']]['Data'] = json_decode($cd['data'], true);
					$mes_struct[$md['id']]['Calibrations'][$cd['id']]['Analid'] = $cd['anal_id'];
					$mes_struct[$md['id']]['Calibrations'][$cd['id']]['StandardAdditionResult'] = $cd['st_add_result'];
					$mes_struct[$md['id']]['Calibrations'][$cd['id']]['Equation'] = json_decode($cd['equation']);
					$mes_struct[$md['id']]['Calibrations'][$cd['id']]['Timestamp'] = $cd['timestamp'];
					$mes_struct[$md['id']]['Calibrations'][$cd['id']]['Img_filename'] = $cd['img_filename'];
					$mes_struct[$md['id']]['Calibrations'][$cd['id']]['Img_updated'] = $cd['img_updated'];
					$mes_struct[$md['id']]['Calibrations'][$cd['id']]['Status'] = 'old';
				}
			}
		}

		$analytes = $this->cPage->db->get(T_ANALYTES,'meta_id=' . $metadata[0]['id']);

		$this->dataStruct['File_name'] = $metadata[0]['file_name'];
		$this->dataStruct['File_upload_timestamp'] = $metadata[0]['file_added'];
		$this->dataStruct['Meta_owner'] = $metadata[0]['owner_id'];
		$this->dataStruct['Meta_id'] = $metadata[0]['id'];
		$this->dataStruct['Mesdata_proc_to_id'] = $proc_id_to_id;
		$this->dataStruct['Mesdata'] = $mes_struct;
		if ( isset($analytes[0]['name']) ) {
			foreach($analytes as $an) {
				$this->dataStruct['Analytes'][$an['id']] = array( 'Name' => $an['name'], 'Conc'=>json_decode($an['concentrations']), 'Disp_units_id' => $an['disp_in_conc_units_id']);
				$this->dataStruct['Analytes'][$an['id']]['Status'] = 'old';
			}
		} else {
			$this->dataStruct['Analytes'] = false;
		}
		$this->dataStruct['Was_processed'] = $metadata[0]['was_processed'];
	}

	public function loadDataOfMes($mes_id)
	{
		if ( isset($this->dataStruct['Mesdata'][$mes_id]) ) {
			$mesdata = $this->cPage->db->get(T_MESDATA,'id='.$mes_id);
			if ( isset($mesdata[0]) ) {
				$this->dataStruct['Mesdata'][$mes_id]['Data'] = json_decode($mesdata[0]['data'],true);
			} else {
				throw new Exception('Data corrupted.');
			}
		} else {
			throw new Exception('Not permited.');
		}
	}

	public function isLoaded()
	{
		if ( isset($this->dataStruct['Meta_id']) && is_numeric($this->dataStruct['Meta_id']) )
			return true;
		return false;
	}

	public function isLoadedMes($mes_id)
	{
		if ( $this->isLoaded() ) {
			if ( isset($this->dataStruct['Mesdata'][$mes_id]) ) {
				return true;
			}
		}
		return false;
	}

	public function isLoadedCal($mes_id, $cal_id)
	{
		if ( $this->isLoaded() ) {
			if ( isset($this->dataStruct['Mesdata'][$mes_id]['Calibrations'][$cal_id]) )
				return true;
		}
		return false;
	}

	public function getCalibrationName($mes_id, $cal_id)
	{
		if ( !isset($this->dataStruct['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Cal_method_id']) )
			throw new Exception('Database error: Cal Meth');
		$cal = $this->cPage->db->get(T_CAL_METHODS, 'id=' . $this->dataStruct['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Cal_method_id']);
		if ( !isset($cal[0]['name']) )
			throw new Exception('Database error: get Cal Meth');
		else 
			return $cal[0]['name'];
	}

	public function getAnalyteConc($mes_id, $cal_id)
	{
		if ( !isset($this->dataStruct['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Analid']) )
			throw new Exception('Database error.1');
		$ret = array();
		if ( $this->dataStruct['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Analid'] == NULL ) {
			return $this->dataStruct['Analytes'];
		} else {
			if ( isset($this->dataStruct['Analytes'][$this->dataStruct['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Analid']]) )
				return array( $this->dataStruct['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Analid'] => $this->dataStruct['Analytes'][$this->dataStruct['Mesdata'][$mes_id]['Calibrations'][$cal_id]['Analid']] );
		}
		throw new Exception('Database error.2');
	}

	public function getMetaId()
	{
		return $this->dataStruct['Meta_id'];
	}

	public function getRawMesId()
	{
		return $this->dataStruct['Mesdata_proc_to_id'][0];
	}

	public function &getStruct()
	{
		return $this->dataStruct;
	}
	
	public function updateDBMesdata($updateData = true)
	{
		$uid = $this->cPage->sesMan->isLoggedAs();
		if ( !is_numeric($uid) || $uid != $this->dataStruct['Meta_owner'] ) {
			throw new Exception('Not permitted.');
		}
		foreach ($this->dataStruct['Mesdata'] as $mid=>&$md) {
			if ( $md['Status'] == 'new' ) { // jest to nowo dodana metoda processingu //
				if ( !isset($md['Data']['Y'][0]) )
					throw new Exception('New MESDATA has to include DATA.');
				$nrofp = count($md['Data']['Y'][0]);
				$nrofc = count($md['Data']['Y']);
				$num = $this->cPage->db->ins(T_MESDATA . '(meta_id,process_id,data,nr_of_curves,nr_of_points,img_filename,img_updated,img_firstPointX,img_firstPointY)'
						,/*VAL:*/ $this->dataStruct['Meta_id'] . ','
							. $md['Process_id'] . ','
							. '"' . $this->cPage->db->esc(json_encode($md['Data'])) . '"'
							. $nrofc . ','
							. $nrofp . ','
							. ($md['Img_filename'] == NULL?'NULL,' : '"' . $md['Img_filename'] . '",')
							. ($md['Img_updated'] == NULL?'0,' : $md['Img_updated'] . ',')
							. ($md['Img_firstPointX'] == NULL?'NULL,' : $md['Img_firstPointX'] . ',')
							. ($md['Img_firstPointY'] == NULL?'NULL' : $md['Img_firstPointY'] )
					);
				if ( is_numeric($num) ) {
					$this->dataStruct[$num] =&$md;
					unset($this->dataStruct[$mid]);
				}
			} elseif ( $md['Status'] == 'updated' ) {
				$this->cPage->db->upd(T_MESDATA
					, 'img_filename=' . ($md['Img_filename'] == NULL?'NULL,' : '"' . $md['Img_filename'] . '",')
						. 'img_updated=' . ($md['Img_updated'] == NULL?'0,' : $md['Img_updated'] . ',')
						. 'img_firstPointX=' . ($md['Img_firstPointX'] == NULL?'NULL,' : $md['Img_firstPointX'] . ',')
						. 'img_firstPointY=' . ($md['Img_firstPointY'] == NULL?'NULL' : $md['Img_firstPointY'])
						. ($updateData?',data="'.$this->cPage->db->esc(json_encode($md['Data'])).'"':'')
					,/*WHERE*/ 'id='.$mid.' LIMIT 1'
					);
			}
		}
	}

	public function updateDBAnalyte( $id, $value ) 
	{
		$uid = $this->cPage->sesMan->isLoggedAs();
		if ( !is_numeric($uid) || $uid != $this->dataStruct['Meta_owner'] ) {
			throw new Exception('Not permitted.');
		}
		$upd = '';
		foreach ( $value as $k=>$v) {
			switch ( $k ) {
			case 'Name':
				$upd .= '`name` = "'.$v.'",';
				break;
			case 'Conc':
				$upd .= '`concentrations` = "'.$this->cPage->db->esc(json_encode($v)).'",';
				break;
			case 'Disp_units_id':
				$upd .= '`disp_in_conc_units_id` = ' . $v . ',';
				break;
			}
		}
		$upd = substr($upd,0,-1);
		return $this->cPage->db->upd(T_ANALYTES, $upd, 'id=' . $id);
	}

	public function addDBAnalyte( $value ) 
	{
		$uid = $this->cPage->sesMan->isLoggedAs();
		if ( !is_numeric($uid) || $uid != $this->dataStruct['Meta_owner'] ) {
			throw new Exception('Not permitted.');
		}
		$add = '';
		$kadd = '';
		foreach ( $value as $k=>$v) {
			switch ( $k ) {
			case 'Name':
				$kadd .= 'name,';
				$add .= '"'.$v.'",';
				break;
			case 'Conc':
				$kadd .= 'concentrations,';
				$add .= '"'.$this->cPage->db->esc(json_encode($v)).'",';
				break;
			case 'Disp_units_id':
				$kadd .= 'disp_in_conc_units_id,';
				$add .= $v . ',';
				break;
			}
		}
		$kadd .= 'meta_id';
		$add .= $this->dataStruct['Meta_id'];
		return $this->cPage->db->ins(T_ANALYTES.'('.$kadd.')', $add);
	}

	public function deleteDBAnalyte($aid)
	{
		if ( isset($this->dataStruct['Analytes'][$aid]) ) {
			return $this->cPage->db->del(T_ANALYTES, 'id=' . $aid);
		}
		return false;
	}

	public function updateDBCaldata($mes_id)
	{
		$uid = $this->cPage->sesMan->isLoggedAs();
		if ( !is_numeric($uid) || $uid != $this->dataStruct['Meta_owner'] ) {
			throw new Exception('Not permitted.');
		}
		if ( !is_numeric($mes_id) || !$this->isLoadedMes($mes_id) || !isset($this->dataStruct['Mesdata'][$mes_id]['Calibrations']) )
			throw new Exception('Data malformed.');
		$updated = false;
		//print_r($this->dataStruct['Mesdata'][$mes_id]['Calibrations']);
		$added = array();
		foreach ( $this->dataStruct['Mesdata'][$mes_id]['Calibrations'] as $key=>&$cal ) {
			if ( ! ( isset($cal['Analid']) 
				 && isset($cal['StandardAdditionResult'])
				 && isset($cal['Equation'])
				 && isset($cal['Img_updated'])
				 && isset($cal['Data'])
				 && isset($cal['Cal_method_id']) 
				)
			) {
				throw new Exception('Caldata incomplete.' . print_r($cal,true));
			}
			if ( !isset($cal['Status']) )
				continue;
			if ( $cal['Status'] == 'updated' ) {
				$upd = $this->cPage->db->upd(T_CALDATA
					, 'data="' . $this->cPage->db->esc(json_encode($cal['Data'])) . '",'
						. 'cal_meth_id=' . $cal['Cal_method_id'] . ','
						. 'mes_id=' . $mes_id . ','
						. 'anal_id=' . $cal['Analid'] . ','
						. 'equation="' . $this->cPage->db->esc(json_encode($cal['Equation'])) . '",'
						. 'img_filename=' . (($cal['Img_filename']==NULL||empty($cal['Img_filename']))?'NULL,':'"' . $this->cPage->db->esc($cal['Img_filename']).'",')
						. 'img_updated=' . $cal['Img_updated']
						. 'st_add_result="'.$this->cPage->db->esc($cal['StandardAdditionResult']) .'"'
					, 'id=' . $key
					);

			} elseif ( $cal['Status'] == 'new' ) {
				$added[] = $this->cPage->db->ins(T_CALDATA . '(cal_meth_id,mes_id,anal_id,data,img_filename,img_updated,equation,st_add_result)'
					, $cal['Cal_method_id'] . ','
						. $mes_id . ','
						. $cal['Analid'] . ','
						. '"' . $this->cPage->db->esc(json_encode($cal['Data'])) . '",'
						. (($cal['Img_filename']==NULL||empty($cal['Img_filename']))?'NULL,':'"' . $this->cPage->db->esc($cal['Img_filename']).'",')
						. $cal['Img_updated'] . ','
						. '"' . $this->cPage->db->esc(json_encode($cal['Equation'])) . '",'
						. '"'.$this->cPage->db->esc($cal['StandardAdditionResult']) .'"'
					);
				if ( is_numeric(end($added)) ) {
					$this->dataStruct['Mesdata'][$mes_id]['Calibrations'][end($added)] = &$cal;
					unset($this->dataStruct['Mesdata'][$mes_id]['Calibrations'][$key]);
				}
			}
		}
		return $added;
	}

	private function updateDataStruct($meta_id = false)
	{
		if ( $meta_id == false )
			$meta_id = $this->dataStruct['Meta_id'];

		unset($this->dataStruct);
		$this->loadStruct($meta_id);
	}

	public function addNewStruct($nds) //new data struct
	{
		$uid = $this->cPage->sesMan->isLoggedAs();
		if ( !is_numeric($uid) ) {
			throw new Exception('Not permitted.');
		}
		if ( !is_array($nds) )
			throw new Exception('Malformed datasctruct.');
		if ( 
			!isset($nds['File_name'])
			|| !isset($nds['Mesdata'])
			|| !isset($nds['Mesdata'][0]['Data']['X'][0][0])
			|| !isset($nds['Mesdata'][0]['Data']['Y'][0][0])
			|| !isset($nds['isCV'])
			|| !is_numeric($nds['isCV'])
			|| !isset($nds['voltType'])
		) {
			throw new Exception('Malformed datastruct.');
		}
		$Estart = $nds['Mesdata'][0]['Data']['X'][0][0];
		$Eend = end($nds['Mesdata'][0]['Data']['X'][0]);
		$Estep = ($Eend-$Estart)/count($nds['Mesdata'][0]['Data']['X'][0]);
		$meta_id = $this->cPage->db->ins(T_METADATA . '(file_name,was_processed,owner_id,is_public,E_start,E_end,E_step,voltammetry_type,is_cv)'
					, '"' . $this->cPage->db->esc($nds['File_name']) . '",'
						. '0,'
						. $uid . ','
						. '0,'
						. $Estart . ','	
						. $Eend . ','
						. $Estep . ','
						. '"' . $this->cPage->db->esc($nds['voltType']) . '",'
						. $nds['isCV']
				);
		if ( $meta_id === false ) {
			//TODO: error handling?
			return false;
		}
		$mes_id = $this->cPage->db->ins(T_MESDATA . '(meta_id,process_id,data,nr_of_curves,nr_of_points,img_filename,img_updated,img_firstPointX,img_firstPointY)'
				, $meta_id . ','
					. '0,'
					. '"' . $this->cPage->db->esc(json_encode($nds['Mesdata'][0]['Data'])) . '",'
					. count($nds['Mesdata'][0]['Data']['Y']) . ','
					. count($nds['Mesdata'][0]['Data']['Y'][0]) . ','
					. ((!isset($nds['Img_filename']) || $nds['Img_filename'] == NULL)?'NULL,' : '"' . $nds['Img_filename'] . '",') 
					. ((!isset($nds['Img_updated']) || $nds['Img_updated'] == NULL)?'0,' : $nds['Img_updated'] . ',')
					. ((!isset($nds['Img_firstPointX']) || $nds['Img_firstPointX'] == NULL)?'NULL,' : $nds['Img_firstPointX'] . ',')
					. ((!isset($nds['Img_firstPointY']) || $nds['Img_firstPointY'] == NULL)?'NULL' : $nds['Img_firstPointY'] )
				);
		if ( $mes_id === false ) {
			//TODO: error handling ?
			return false;
		}
		if ( isset($nds['Analytes'][0]) ) {
			foreach($nds['Analytes'] as $an) {
				$this->cPage->db->ins(T_ANALYTES . '(meta_id,name,concentrations)',$meta_id. ',"'.$this->cPage->db->esc($an['Name']) .'","'.$this->cPage->db->esc(json_encode($nds['Conc'])) .'"');
			}
		}
		$this->updateDataStruct($meta_id);
	}

	function evalEq( $equation, $x)
	{
		if ( !isset($equation->_type) ) {
			throw new Exception('Wrong equation type.');
		}
		switch ($equation->_type) {
		case 'y=bx+a':
			return $equation->b*$x+$equation->a;
		default:
			throw new Exception('Unknown equation type');
		}
	}
	
	function dispEq($equation) 
	{
		 if ( !isset($equation->_type) ) {
                        throw new Exception('Wrong equation type.');
                }
                switch ($equation->_type) {
                case 'y=bx+a':
			return 'y=' . $equation->b .'*x+' . $equation->a .' (r=' . $equation->r.')';
		default:
			throw new Exception('Unknown equation type');
		}
	}
}


