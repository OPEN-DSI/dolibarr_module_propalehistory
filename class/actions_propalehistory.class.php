<?php
class ActionsPropalehistory
{ 
     /** Overloading the doActions function : replacing the parent's function with the one below 
      *  @param      parameters  meta datas of the hook (context, etc...) 
      *  @param      object             the object you want to process (an invoice if you are in invoice module, a propale in propale's module, etc...) 
      *  @param      action             current action (if set). Generally create or edit or null 
      *  @return       void 
      */
      
    function formObjectOptions($parameters, &$object, &$action, $hookmanager) 
    {
      	global $langs,$db;
		define('INC_FROM_DOLIBARR', true);
		dol_include_once("/propalehistory/config.php");
		dol_include_once("/comm/propal/class/propal.class.php");
		require_once("propaleHist.class.php");
		$ATMdb = new TPDOdb;
		
		if (in_array('propalcard',explode(':',$parameters['context']))) 
        {
	        if($action != 'create') {	
	        	isset($_REQUEST['actionATM'])?$actionATM = $_REQUEST['actionATM']:$actionATM = '';
				if($actionATM == 'viewVersion') {
					?>
						<script type="text/javascript">
							$(document).ready(function() {
								$('div.tabsAction').html('<?='<a id="butRestaurer" class="butAction" href="'.DOL_URL_ROOT.'/comm/propal.php?id='.$_REQUEST['id'].'&actionATM=restaurer&idVersion='.$_REQUEST['idVersion'].'">Restaurer</a>'?>');
							})
						</script>
					
					<?	
					$this->listeVersions($db, $object);			
				} elseif($actionATM == 'createVersion') {
					$this->listeVersions($db, $object);
				} elseif($actionATM == '' && $object->statut == 1) {
					print '<a id="butNewVersion" class="butAction" href="'.DOL_URL_ROOT.'/comm/propal.php?id='.$_REQUEST['id'].'&actionATM=createVersion">Archiver</a>';
					?>
						<script type="text/javascript">
							$(document).ready(function() {
								$("#butNewVersion").appendTo('div.tabsAction');
							})
						</script>
					<?
					$this->listeVersions($db, $object);				
				}
			}

		}
		
		return 0;
	}

	function doActions($parameters, &$object, &$action, $hookmanager) {
      	global $langs,$db, $user;
		define('INC_FROM_DOLIBARR', true);
		dol_include_once("/propalehistory/config.php");
		dol_include_once("/comm/propal/class/propal.class.php");
		require_once("propaleHist.class.php");
		$ATMdb = new TPDOdb;
		
		if(isset($_REQUEST['actionATM'])) {
			$actionATM = $_REQUEST['actionATM'];
		} else {
			$actionATM = '';
		}
		
		if($actionATM == 'viewVersion') {
			
			$version = new TPropaleHist;
			$version->load($ATMdb, $_REQUEST['idVersion']);
			$object = unserialize($version->serialized_parent_propale);
			$object->__construct($db, $object->socid);
									
		} elseif($actionATM == 'createVersion') {
			$this->archiverPropale($object);
		} elseif($actionATM == 'restaurer') {
			$this->restaurerPropale($object);
		}
	} 

	function archiverPropale(&$object) {
		
		$ATMdb = new TPDOdb;
		
		$newVersionPropale = new TPropaleHist;
		$newVersionPropale->serialized_parent_propale = serialize($object);
		$newVersionPropale->date_version = dol_now();
		$newVersionPropale->fk_propale = $object->id;
		$newVersionPropale->save($ATMdb);
		if($_REQUEST['actionATM'] == 'createVersion') {
			setEventMessage('Version sauvegardée avec succès !', 'mesgs');
		} elseif($_REQUEST['actionATM'] == 'restaurer') {
			setEventMessage('Restauration effectuée avec succès !', 'mesgs');
		}

	}
	
	function restaurerPropale(&$object) {
		
		global $db, $user;
		$ATMdb = new TPDOdb;
		
		$versionPropale = new TPropaleHist;
		$versionPropale->load($ATMdb, $_REQUEST['idVersion']);
		$propale = unserialize($versionPropale->serialized_parent_propale);
		$propale->statut = 0;
		
		$this->archiverPropale($object);
				
		$ancienID = $object->id;
		$ancienneRef = $object->ref;
						
		$object->delete($user);
		
		$propale->__construct($db, $propale->socid);
		$propale->create($user);
		$propale->valid($user);
		
		// On récupère la référence de la propale une fois validée car la fonction valid() ne stocke pas la nouvelle ref dans l'objet...
		$sql = "SELECT rowid, ref";
		$sql.= " FROM ".MAIN_DB_PREFIX."propal";
		$sql.= " WHERE rowid = ".$propale->id;
		$resql = $db->query($sql);
		$resql = $resql->fetch_object();
		$nouvelID = $resql->rowid;
		$nouvelleRef = $resql->ref;

		// On remet la même référence qu'au début
		$sql = "UPDATE ".MAIN_DB_PREFIX."propal";
		$sql.= " SET ref = '".$ancienneRef;
		$sql.= "' WHERE ref = '".$nouvelleRef."'";
		$resql = $db->query($sql);
		
		// On met à jour le champ fk_propale de la table llx_propale_history pour chaque version de la propale concernée
		$sql = "UPDATE ".MAIN_DB_PREFIX."propale_history";
		$sql.= " SET fk_propale = ".$nouvelID;
		$sql.= " WHERE fk_propale = ".$ancienID;
		$resql = $db->query($sql);
		
		?>
			<script language="javascript">
				document.location.href="<?=dirname($_SERVER['PHP_SELF'])?>/propal.php?id=" + <?=$nouvelID?>;
			</script>
		<?		

	}
	
	function listeVersions(&$db, $object) {
		//print_r($object);
		$sql = "SELECT rowid, date_version, date_cre";
		$sql.= " FROM ".MAIN_DB_PREFIX."propale_history";
		$sql.= " WHERE fk_propale = ".$_REQUEST['id'];
		$sql.= " ORDER BY 1 ASC";
		$resql = $db->query($sql);
		
		print '<form name="formVoirPropale" method="POST" action="'.DOL_URL_ROOT.'/comm/propal.php?id='.$_REQUEST['id'].'">';
		print '<input type="hidden" name="actionATM" value="viewVersion" />';
		print '<input type="hidden" name="socid" value="'.$object->socid.'" />';
		print '<select name="idVersion">';
		while($row = $resql->fetch_object()) {
			
			if(isset($_REQUEST['idVersion']) && $_REQUEST['idVersion'] == $row->rowid){
				$selected = 'selected="selected"';
			} else {
				$selected = "";
			}
			echo $selected;
			print '<option id="'.$row->rowid.'" value="'.$row->rowid.'" '.$selected.'>'.$row->rowid.' : '.$row->date_cre.'</option>';
			
		}
		print '</select>';
		print '<input value="Voir" type="SUBMIT" />';
		print '</form>';
		
	}
     
    function formEditProductOptions($parameters, &$object, &$action, $hookmanager) 
    {
		
    	if (in_array('invoicecard',explode(':',$parameters['context'])))
        {
        	
        }
		
        return 0;
    }

	function formAddObjectLine ($parameters, &$object, &$action, $hookmanager) {
		
		global $db;
		
		if (in_array('ordercard',explode(':',$parameters['context'])) || in_array('invoicecard',explode(':',$parameters['context']))) 
        {
        	
        }

		return 0;
	}

	function printObjectLine ($parameters, &$object, &$action, $hookmanager){
		
		global $db;
		
		if (in_array('ordercard',explode(':',$parameters['context'])) || in_array('invoicecard',explode(':',$parameters['context']))) 
        {
        	
        }

		return 0;
	}
}