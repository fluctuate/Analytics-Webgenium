<?php
/*------------------------------------------------------------------------
# com_analytics - Webgenium Analytics
# ------------------------------------------------------------------------
# author    Luiz Felipe Weber - Webgenium System
# copyright Copyright (C) 2011 webgenium.com.br. All Rights Reserved.
# @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
# Websites: http://loja.weber.eti.br / http://webgenium.com.br
# Technical Support:  Forum - https://github.com/webgenium/Analytics-Webgenium
-------------------------------------------------------------------------*/

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Analytics Helper
 *
 * @package Joomla
 * @subpackage Analytics
 * @since 1.5
 */
class AnalyticsHelper{
    public function __construct() {
	}

    /**
     * Envia o email notificando o administrador do site
     * @param array $row
     * @param string $conteudo
     * @return phpmailer
     */
    public function sendMail($row, $conteudo) {

        $mailer =& JFactory::getMailer();
        $sender = array($row->mailfrom, $row->fromname);
        $mailer->setSender($sender);
        $mailer->addRecipient($row->mailfrom);

        $mailer->setSubject("Estat�sticas ".$row->sitename);
        $mailer->setBody($conteudo);
        $mailer->isHTML(true);

        $send =& $mailer->Send();
        if ( $send !== true ) {
                return 'Error sending email: ' . $send->message;
        } else {
                return 'Mail sent';
        }
    }
	
	function replaceMootools() { 
		$document =&JFactory::getDocument();
		unset($document->_scripts['../media/system/js/mootools.js']);
	}
	
	function errorAnalytics($erro){
		$component = JRequest::getVar('option');
		$configure = '- <a href="index.php?option=com_config&controller=component&component='.$component.'&path=">configure</a>';
		JError::raiseWarning(E_WARNING, '[Google Analytics] - '.JText::_($erro).$configure);
	}

	function noticeAnalytics($notice){
		//JError::raiseWarning(E_NOTICE, '[Google Analytics] - '.JText::_($notice));
		JFactory::getApplication()->enqueueMessage( '[Google Analytics] - '.JText::_($notice) );
	}	

	function getHost() {
		// pega o host
		$host = str_replace('/administrator/','',str_replace('http://','',JURI::base()));
		return $host;
	}

	function getConfiguracaoAnalytics() {
		$params =& JComponentHelper::getParams(JRequest::getVar('option'));
		return $params;
	}

	function setConfiguracaoAnalytics($params) {
		// salvar configuracoes no parametro
		$component = JRequest::getVar( 'option' );
		$table =& JTable::getInstance('component');
		if (!$table->loadByOption( $component )) {
			JError::raiseWarning( 500, 'Not a valid component' );
		}
		
		$post = array();
		// vetor com os dados para salvar
		$post['params']['ga_account_id'] 	= $params['ga_account_id'];
		$post['params']['webPropertyId'] 	= $params['webPropertyId'];
		$post['params']['ga_email'] 			= $params['ga_email'];
		$post['params']['ga_pass'] 				= $params['ga_pass'];

		$post['option'] 		= $component;
		$table->bind( $post );

		// pre-save checks
		if (!$table->check()) {
			JError::raiseWarning( 500, $table->getError() );
		}

		// save the changes
		if (!$table->store()) {
			JError::raiseWarning( 500, $table->getError() );
		}
	}
	
	function google_charts() {	
		$um_mes_atras = date('Y-m-d',strtotime('1 month ago'));
		$data_atual = date('Y-m-d');

		$mes_atras_relatorio  = date("d/m/Y",strtotime('1 month ago'));
		$data_relatorio  = date("d/m/Y");

		// recupera a visao principal do componente pra enviar as estatisticas
		$view = new AnalyticsViewDefault;
		// maximo de 10 metricas
		$relatorio = $view->novoRelatorio (
								'grafico',
								array('date'),  // dimensions
								array('visits' ,'pageviews' ), // metrics
								'-date',	null, $um_mes_atras, $data_atual, 1, 31, false);
		// visualizacoes
		$minvalue = 999999999;
		$maxvalue = 0;
		$count_z = 0;
		$cvals_z = '';	
		
		// visitas
		$count_v = 0;
		$cvals_v = '';
		$total = count($relatorio);
		foreach($relatorio as $grafico) {
			if($minvalue > $grafico->getPageviews()) {
				$minvalue = $grafico->getPageviews();
			}
			if($maxvalue < $grafico->getPageviews()) {
				$maxvalue = $grafico->getPageviews();
			}
			$cvals_z .= $grafico->getPageviews() . ($count_z < $total-1 ? "," : "");
			$count_z++;
			
			if($minvalue > $grafico->getVisits()) {
				$minvalue = $grafico->getVisits();
			}
			if($maxvalue < $grafico->getVisits()) {
				$maxvalue = $grafico->getVisits();
			}
			$cvals_v .= $grafico->getVisits() . ($count_v < $total-1 ? "," : "");
			$count_v++;
		}
		$visualizacoes = $cvals_z;
		$visitas = $cvals_v;

		$url = str_replace('administrator/','',JURI::base());
		return '<img src="'.$url.'administrator/components/com_analytics/helpers/google_charts.php?visualizacoes='.$visualizacoes.'&visitas='.$visitas.'&start_format='.$mes_atras_relatorio.'&end_format='.$data_relatorio.'&minimo='.$minvalue.'&maximo='.$maxvalue.'"/>';
	}
	
	 function sec2hms ($sec, $padHours = false)  {
		$hms = "";		
		$hours = intval(intval($sec) / 3600); 
		$hms .= ($padHours) 
			  ? str_pad($hours, 2, "0", STR_PAD_LEFT). ":"
			  : $hours. ":";
		$minutes = intval(($sec / 60) % 60); 
		$hms .= str_pad($minutes, 2, "0", STR_PAD_LEFT). ":";
		$seconds = intval($sec % 60); 
		$hms .= str_pad($seconds, 2, "0", STR_PAD_LEFT);
		return $hms;    
	}	

	function reformataData($data){	
		//return substr($data,6,2).'/'.substr($data,4,2).'/'.substr($data,0,4);
		return date('j M',strtotime($data));
	}
	
	
}
?>