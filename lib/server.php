<?php
/**
 * ownCloud / SabreDAV
 *
 * @author Markus Goetz
 *
 * @copyright Copyright (C) 2007-2013 Rooftop Solutions. All rights reserved.
 * @author Evert Pot (http://www.rooftopsolutions.nl/)
 * @license http://code.google.com/p/sabredav/wiki/License Modified BSD License
 */

/**
 * Class OC_Connector_Sabre_Server
 *
 * This class reimplements some methods from @see \Sabre\DAV\Server.
 *
 * Basically we add handling of depth: infinity.
 *
 * The right way to handle this would have been to submit a patch to the upstream project
 * and grab the corresponding version one merged.
 *
 * Due to time constrains and the limitations where we don't want to upgrade 3rdparty code in
 * this stage of the release cycle we did choose this approach.
 *
 * For ownCloud 7 we will upgrade SabreDAV and submit the patch - if needed.
 *
 * @see \Sabre\DAV\Server
 *
 * This class is a modified version of OC_Connector_Sabre_Server, described above.
 * It allows hiding folders from sync clients and does not set the mime type of html files to
 * text/plain (potential security risk).
 *
 */
class OC_Connector_Sabre_Server_chooser extends Sabre\DAV\Server {
	
	const NS_NEXTCLOUD = 'http://nextcloud.org/ns';
	
	public function invokeMethod($method, $uri) {
		
		$method = strtoupper($method);
		
		if (!$this->broadcastEvent('beforeMethod',array($method, $uri))) return;
		
		// Make sure this is a HTTP method we support
		$internalMethods = array(
				'OPTIONS',
				'GET',
				'HEAD',
				'DELETE',
				'PROPFIND',
				'MKCOL',
				'PUT',
				'PROPPATCH',
				'COPY',
				'MOVE',
				'REPORT',
				'SEARCH'
		);
		
		if (in_array($method,$internalMethods)) {
			
			call_user_func(array($this,'http' . $method), $uri);
			
		} else {
			
			if ($this->broadcastEvent('unknownMethod',array($method, $uri))) {
				// Unsupported method
				throw new \Sabre\DAV\Exception\NotImplemented('There was no handler found for this "' . $method . '" method');
			}
			
		}
		
	}
	
	private $mediaSearch = false;
	private $searchDepth = "";
	// This is only for the media search called by the iPhone app.
	protected function httpSearch($uri) {
		$this->mediaSearch = true;
		$xml = $this->httpRequest->getBody(true);
		
		$xml = str_replace('<d:searchrequest', '<d:propfind', $xml);
		$xml = str_replace('</d:searchrequest', '</d:propfind', $xml);
		$xml = str_replace('<d:basicsearch>', '', $xml);
		$xml = str_replace('</d:basicsearch>', '', $xml);
		$xml = str_replace('<d:select>', '', $xml);
		$xml = str_replace('</d:select>', '', $xml);
		$this->httpRequest->setBody($xml);
		OC_Log::write('chooser','Media search XML: '.$xml, OC_Log::WARN);
		$xml = preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $xml);
		$parsed = simplexml_load_string($xml);
		$parsed = dom_import_simplexml($parsed);
		$parsed->preserveWhiteSpace = false;
		
		$scope = $parsed->getElementsByTagName('from')->item(0)->getElementsByTagName('scope')->item(0);
		$path = $scope->getElementsByTagName('href')->item(0)->nodeValue;
		$this->searchDepth = $scope->getElementsByTagName('depth')->item(0)->nodeValue;
		$user = \OCP\USER::getUser();
		$path = preg_replace('|/files/'.$user.'|', '', $path);
		OC_Log::write('chooser','Search path: '.$path, OC_Log::WARN);
		
		$orderby = $parsed->getElementsByTagName('orderby')->item(0)->getElementsByTagName('order');
		if(!empty($orderby)){
			foreach($orderby as $order){
				foreach($order->getElementsByTagName('prop') as $p){
					foreach($p->childNodes as $node){
						if($node->nodeName!='#text' && !empty($node->nodeName)){
							//{DAV:}lastModified
							$prop = '{'.$node->namespaceURI.'}'.preg_replace('|^[^:]+:|', '', $node->nodeName);
							break;
						}
					}
				}
				$direction = $order->getElementsByTagName('ascending')->length==1?'ascending':'descending';
				$this->orderBy[] = ['prop'=>$prop, 'direction'=>$direction];
				OC_Log::write('chooser','ORDERBY: '.$prop.':'.$direction, OC_Log::WARN);
			}
		}
		
		$limit = '';
		$limits = $parsed->getElementsByTagName('limit');
		if($limits->length>0){
			$limit = $limits->item(0)->getElementsByTagName('nresults')->item(0)->nodeValue;
		}
		OC_Log::write('chooser', 'Search limit: '.$limit, OC_Log::WARN);
		if(!empty($limit)){
			$this->limit = $limit;
		}
		
		$where = $this->parseWhere($parsed->getElementsByTagName('where')->item(0));
		OC_Log::write('chooser', 'Search conditions: '.$where, OC_Log::WARN);
		if(!empty($where)){
			$this->where = $where;
		}
		
		if($this->mediaSearch){
			$this->httpPropfind($path);
		}
	}
	
	private function parseWhere($where){
		$name = preg_replace('/^[^:]+:/', '', $where->nodeName);		
		if($name=='and' || $name=='or'){
			$arr = [];
			foreach($where->childNodes as $node){
				$el = $this->parseWhere($node);
				if(!empty($el)){
					$arr[] = $el;
				}
			}
			return '( ' . join(' '.$name.' ', $arr). ' )';
		}
		elseif($name=='like' || $name=='lt'|| $name=='gt'){
			foreach($where->getElementsByTagName('prop')->item(0)->childNodes as $node){
				if($node->nodeName!='#text' && !empty($node->nodeName)){
					$prop = '{'.$node->namespaceURI.'}'.preg_replace('|^[^:]+:|', '', $node->nodeName);
					break;
				}
			}
			$literal = $where->getElementsByTagName('literal')->item(0)->nodeValue;
			return $prop.' '.$name.' '.$literal;
		}
		else{
			foreach($where->childNodes as $node){
				if($node->nodeName!='#text' && !empty($node->nodeName)){
					return $this->parseWhere($node);
				}
			}
		}
	}
	
	protected function httpProppatch($uri) {
		/*
		
		curl -u test2:some_password -X PROPFIND --data-binary '<?xml version="1.0" ?><d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns"><d:prop><d:resourcetype /><d:getlastmodified /><d:getcontentlength /><d:getetag /><oc:size /><oc:id /><oc:fileid /><oc:downloadURL /><oc:dDC /><oc:permissions /><oc:checksums /><is-encrypted xmlns="http://nextcloud.org/ns" /><oc:share-types /><is-mount-root xmlns="http://nextcloud.org/ns" /></d:prop></d:propfind>' https://silo2.sciencedata.dk/sharingin/remote.php/dav/files/test2
		
		*/
		$xml = $this->httpRequest->getBody(true);
		OC_Log::write('chooser','Proppatch XML: '.$xml, OC_Log::WARN);
		$newProperties = $this->parsePropPatchRequest($xml);
		
		$favProp = '{' . \OC_Connector_Sabre_FilesPlugin::NS_OWNCLOUD . '}favorite';
		if(isset($newProperties[$favProp]) &&
				OCP\App::isEnabled('internal_bookmarks')){
			OC_Log::write('chooser','FAVORITE: '.$uri, OC_Log::WARN);
			$info = \OC\Files\Filesystem::getFileInfo($uri);
			if($info->getType()=='dir'){
				require_once 'apps/internal_bookmarks/lib/intbks.class.php';
				$bookmark = \OC_IntBks::getItemByTarget('/'.rtrim($uri, '/'));
				if(empty($newProperties[$favProp]) && !empty($bookmark)){
					\OC_IntBks::deleteItemByTarget(rtrim($uri, '/'), false);
					$newProperties[$favProp] = null;
				}
				elseif($newProperties[$favProp]==1 && empty($bookmark)){
					\OC_IntBks::insertNewItem('/'.rtrim($uri, '/'), false);
				}
			}
		}
		
		$result = $this->updateProperties($uri, $newProperties);
		
		$prefer = $this->getHTTPPrefer();
		$this->httpResponse->setHeader('Vary','Brief,Prefer');
		
		if ($prefer['return-minimal']) {
			
			// If return-minimal is specified, we only have to check if the
			// request was succesful, and don't need to return the
			// multi-status.
			$ok = true;
			foreach($result as $code=>$prop) {
				if ((int)$code > 299) {
					$ok = false;
				}
			}
			
			if ($ok) {
				
				$this->httpResponse->sendStatus(204);
				return;
				
			}
			
		}
		
		$this->httpResponse->sendStatus(207);
		$this->httpResponse->setHeader('Content-Type','application/xml; charset=utf-8');
		
		$this->httpResponse->sendBody(
				$this->generateMultiStatus(array($result))
				);
	}
	
	// This is only for favorite search from the iPhone app
	/*Test:

curl -u test2:some_password --data-binary '<?xml version="1.0"?><oc:filter-files xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns" xmlns:nc="http://nextcloud.org/ns"><d:prop>    <d:getlastmodified />    <d:getetag />    <d:getcontenttype />    <d:resourcetype />    <d:quota-available-bytes />    <d:quota-used-bytes />    <permissions xmlns="http://owncloud.org/ns"/>    <id xmlns="http://owncloud.org/ns"/>    <fileid xmlns="http://owncloud.org/ns"/>    <size xmlns="http://owncloud.org/ns"/>    <favorite xmlns="http://owncloud.org/ns"/>    <share-types xmlns="http://owncloud.org/ns"/>    <owner-id xmlns="http://owncloud.org/ns"/>    <owner-display-name xmlns="http://owncloud.org/ns"/>    <comments-unread xmlns="http://owncloud.org/ns"/>    <creation_time xmlns="http://nextcloud.org/ns"/>    <upload_time xmlns="http://nextcloud.org/ns"/>    <is-encrypted xmlns="http://nextcloud.org/ns"/>    <has-preview xmlns="http://nextcloud.org/ns"/>    <mount-type xmlns="http://nextcloud.org/ns"/>    <rich-workspace xmlns="http://nextcloud.org/ns"/></d:prop><oc:filter-rules>    <oc:favorite>1</oc:favorite></oc:filter-rules></oc:filter-files>' -X REPORT https://silo2.sciencedata.dk/remote.php/dav/files/test2

	 */
	private $favoriteSearch = false;
	protected function httpReport($uri) {
		$xml = $this->httpRequest->getBody(true);
		OC_Log::write('chooser','Favorite search XML: '.$xml, OC_Log::WARN);
		$xml = preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $xml);
		$parsed = simplexml_load_string($xml);
		$parsed = dom_import_simplexml($parsed);
		$favorite = $parsed->getElementsByTagName('filter-rules')->
			item(0)->getElementsByTagName('favorite')->item(0)->nodeValue;
		OC_Log::write('chooser','Favorite: '.$favorite.'-->'.$this->getBaseUri().
					'-->'.$uri, OC_Log::WARN);
		if($favorite=="1"){
			$this->favoriteSearch = true;
			$this->httpPropfind($uri);
		}
	}
	
	protected function httpOptions($uri) {
		$this->httpResponse->setHeader('dasl', '<DAV:basicsearch>');
		return parent::httpOptions($uri);
	}
	
	protected function httpMove($uri) {
		if(strlen($uri)>4 && substr($uri, -5, 5)=='.file'){
			$moveInfo = $this->getCopyAndMoveInfo();
			$path = rtrim($uri,'/.file');
			OC_Log::write('chooser','Moving: '.$path, OC_Log::WARN);
			$children = $this->tree->getChildren($path);
			usort($children, function($a, $b) {
				return $a->getName() > $b->getName();
			});
			$fileContent = '';
			$user = \OCP\USER::getUser();
			if(!empty($this->tree->group) && $this->tree->group){
				$group_dir = "/" . $user . "/user_group_admin/".$this->tree->group;
				$view = new \OC\Files\View($group_dir);
			}
			else{
				$view = \OC\Files\Filesystem::getView();
			}
			$dataDir = \OC_Config::getValue("datadirectory", \OC::$SERVERROOT . "/data");
			$destination = $view->getAbsolutePath($moveInfo['destination']);
			$fp1 = fopen($dataDir.$destination, 'x');
			foreach($children as $childNode){
				if(!preg_match('|[0-9]+|', $childNode->getName())){
					continue;
				}
				$absChunkPath = $view->getAbsolutePath($path . '/' . $childNode->getName());
				OC_Log::write('chooser','Merging: '.$dataDir.$absChunkPath, OC_Log::WARN);
				$fileContent = file_get_contents($dataDir.$absChunkPath);
				fwrite($fp1, $fileContent);
			}
			fclose($fp1);
			list($storage, $internalPath) = $view->resolvePath('/'.$moveInfo['destination']);
			if($storage){
				OC_Log::write('chooser','Writing: '.$storage->getCache()->getNumericStorageId().'-->'.$moveInfo['destination'].' : '.$dataDir.$destination, OC_Log::WARN);
				$scanner = $storage->getScanner($internalPath);
				$fileData = $scanner->scanFile($internalPath);
				$this->httpResponse->setHeader('oc-fileid', $fileData['fileid']);
				$this->httpResponse->setHeader('etag', '"'.$fileData['etag'].'"');
				$this->httpResponse->setHeader('oc-etag', '"'.$fileData['etag'].'"');
				$this->httpResponse->setHeader('content-type', $fileData['mimetype']);
				$this->httpResponse->sendStatus(201);
			}
			// Now delete the chunks directory
			foreach($children as $childNode){
				if(!preg_match('|[0-9]+|', $childNode->getName())){
					continue;
				}
				$absChunkPath = $view->getAbsolutePath($path . '/' . $childNode->getName());
				unlink($dataDir.$absChunkPath);
			}
			$absChunksDir = $view->getAbsolutePath($path);
			rmdir($absChunksDir);
		}
		else{
			return parent::httpMove($uri);
		}
	}
	
	// Array like [['prop'=>'{DAV:}getlastmodified', 'direction'=>'ascending'], ['prop'=>'{DAV:}displayname', 'direction'=>'ascending'], ...]
	private $orderBy = [];
	/**
	 * Ccompare an two property lists
	 * @param string $properties1
	 * @param string $properties2
	 * @param string $orderBy
	 * @param string $order
	 * @return -1 if 1 is smaller than 2, 1 if larger, 0 if equal
	 */
	private function cmp($properties1, $properties2, $prop, $direction){
		if($prop=='{DAV:}getlastmodified'){
			//$time1 = strtotime($properties1[200][$prop]);
			$time1 = $properties1[200][$prop]->getTime();
			$prop1 = date('Y-m-d', $time1->getTimeStamp());
			//$time2 = strtotime($properties2[200][$prop]);
			$time2 = $properties2[200][$prop]->getTime();
			$prop2 = date('Y-m-d', $time2->getTimeStamp());
			OC_Log::write('chooser','Compare: '.$time1->getTimeStamp().'-->'.serialize($properties1[200][$prop]), OC_Log::DEBUG);
		}
		else{
			$prop1 = $properties1[200][$prop];
			$prop2 = $properties2[200][$prop];
		}
		OC_Log::write('chooser','Comparing: '.$prop1.'-->'.$prop2, OC_Log::INFO);
		if($prop1==$prop2){
			return 0;
		}
		elseif($direction=='ascending' && $prop1<$prop2 || $direction=='descending' && $prop1>$prop2){
			return -1;
		}
		elseif($direction=='ascending' && $prop1>$prop2 || $direction=='descending' && $prop1<$prop2){
			return 1;
		}
		return 0;
	}

	private function cmps($a, $b){
		if(!empty($this->orderBy)){
			foreach($this->orderBy as $ob){
				$cmpRes = $this->cmp($a, $b, $ob['prop'], $ob['direction']);
				if($cmpRes!=0){
					return $cmpRes;
				}
			}
		}
		return 0;
	}
	
	private $limit = 0;
	private $where;
	
	/**
	 * @see \Sabre\DAV\Server
	 */
	protected function httpPropfind($uri) {
		// $xml = new \Sabre\DAV\XMLReader(file_get_contents('php://input'));
		$xml = $this->httpRequest->getBody(true);
		if(!empty($xml)){
			OC_Log::write('chooser','PROPFIND XML: '.$xml, OC_Log::INFO);
		}
		$requestedProperties = $this->parsePropFindRequest($xml);

		$depth = empty($this->searchDepth)?($this->favoriteSearch?1:$this->getHTTPDepth(1)):$this->searchDepth;
		// The only two options for the depth of a propfind is 0 or 1
		// if ($depth!=0) $depth = 1;
		
		if(array_key_exists('REDIRECT_URL', $_SERVER)){
			$redirect_uri = preg_replace('/^https*:\/\/[^\/]+\//', '/', $_SERVER['REDIRECT_URL']);
			if(strpos($redirect_uri, OC::$WEBROOT."/remote.php/webdav/")===0 ||
						$redirect_uri==OC::$WEBROOT."/remote.php/webdav"){
				//$redirect_url = preg_replace('|'.OC::$WEBROOT.'/mydav/|', OC::$WEBROOT.'/webdav/', $_SERVER['REDIRECT_URL'], 1);
				$this->setBaseUri(OC::$WEBROOT."/remote.php/webdav");
			}
		}
		
		//$newProperties['href'] = preg_replace('/^(\/*remote.php\/)mydav\//', '$1/wdav/', trim($myPath,'/'));
		
		$newProperties = $this->getPropertiesForPath($uri, $requestedProperties, $depth);
		
		if(!empty($this->orderBy)){
			usort($newProperties, array( $this, 'cmps'));
		}
		
		if(!empty($this->limit)){
			$newProperties = array_slice($newProperties, 0, (int)$this->limit);
		}

		// This is a multi-status response
		$this->httpResponse->sendStatus(207);
		$this->httpResponse->setHeader('Content-Type','application/xml; charset=utf-8');
		$this->httpResponse->setHeader('Vary','Brief,Prefer');

		// Normally this header is only needed for OPTIONS responses, however..
		// iCal seems to also depend on these being set for PROPFIND. Since
		// this is not harmful, we'll add it.
		$features = array('1','3', 'extended-mkcol');
		foreach($this->plugins as $plugin) {
			$features = array_merge($features,$plugin->getFeatures());
		}

		$this->httpResponse->setHeader('DAV',implode(', ',$features));

		$prefer = $this->getHTTPPrefer();
		$minimal = $prefer['return-minimal'];

		$data = $this->generateMultiStatus($newProperties, $minimal);
		
		if(!empty($this->tree->sharingInOut) && $this->tree->sharingInOut){
			$data = str_replace('<d:href>/sharingout/', '<d:href>/sharingin/', $data);
		}
		if(!empty($this->tree->sharingIn) && $this->tree->sharingIn){
			$data = str_replace('<oc:permissions></oc:permissions>', '<oc:permissions>S</oc:permissions>', $data);
		}
		OC_Log::write('chooser','PROPFIND: '.$data, OC_Log::DEBUG);
		if($this->favoriteSearch){
			$user = \OCP\USER::getUser();
			$data = str_replace('<d:href>'.OC::$WEBROOT.'/remote.php/mydav/',
					'<d:href>'.OC::$WEBROOT.'/remote.php/dav/files/'.$user.'/', $data);
			$data = str_replace('<d:href>', "<d:status>HTTP/1.1 200 OK</d:status>\n<d:href>", $data);
			$data = str_replace('<d:href>'.OC::$WEBROOT.'/remote.php/dav/files/'.$user.'/%40%40/',
					'<d:href>'.OC::$WEBROOT.'/remote.php/dav/files/'.$user.'/@@/', $data);
		}
		if($this->mediaSearch){
			$user = \OCP\USER::getUser();
			$data = str_replace('<d:href>'.OC::$WEBROOT.'/remote.php/mydav/', '<d:href>'.OC::$WEBROOT.'/remote.php/dav/files/'.
					$user.'/', $data);
		}
		if(!empty($this->tree->usage) && $this->tree->usage){
			$data = str_replace('<d:href>'.OC::$WEBROOT.'/remote.php/usage',
					'<d:href>'.OC::$WEBROOT.'/remote.php/usage/remote.php/webdav', $data);
		}
		if(!empty($this->tree->group) && $this->tree->group){
			$data = str_replace('<d:href>'.OC::$WEBROOT.'/remote.php/groupfolders',
					'<d:href>'.OC::$WEBROOT.'/remote.php/groupfolders/remote.php/webdav', $data);
		}
		//OC_Log::write('chooser','SENDING DATA: '.$_SERVER['HTTP_USER_AGENT'].':'.$user.':'.$uri.'-->'.$data, OC_Log::INFO);
		$this->httpResponse->sendBody($data);

	}

	/**
	 * Small helper to support PROPFIND with DEPTH_INFINITY.
	 */
	private function addPathNodesRecursively(&$nodes, $path) {
		// For depth inifinity requests on /sharingin/some_user,
		// just return up to /sharingin/some_user/some_share1, ...
		// Beyond that we'll be redirecting
		if(!empty($this->tree->sharingIn) && $this->tree->sharingIn &&
				substr_count($path, '/')>0){
			return;
		}
		OC_Log::write('chooser','Adding children of: '.$path.'-->'.get_class($nodes[$path]), OC_Log::INFO);
		foreach($this->tree->getChildren($path) as $childNode) {
			if($this->excludePath($path) || $this->excludePath($path . '/' . $childNode->getName())){
				continue;
			}
			if(!$this->mediaSearch || ($childNode instanceof \OC_Connector_Sabre_File &&
					(substr($childNode->getContentType(), 0, 6)=="image/" ||
							substr($childNode->getContentType(), 0, 6)=="movie/" ||
							substr($childNode->getContentType(), 0, 6)=="video/"
							))){
					OC_Log::write('chooser','Adding child: '.$path.'/' . $childNode->getName().'-->'.get_class($childNode), OC_Log::INFO);
					$nodes[$path . '/' . $childNode->getName()] = $childNode;
			}
			if ($childNode instanceof \Sabre\DAV\ICollection)
				$this->addPathNodesRecursively($nodes, $path . '/' . $childNode->getName());
		}
	}
	
	/**
	* Small helper to reverse getSecureMimeType for html files - i.e. set
	* Content-type to text/html instead of the more secure text/plain.
	*/
	private static function unsecureContentType($filepath, $contentType){
		if(strtolower(substr($filepath, -5))===".html"){
			$contentType = "text/html";
			//$contentType = "";
		}
		// Fix up quicktime files, so iPads, iPhones will play
		elseif(strtolower(substr($filepath, -4))===".mov"){
			$contentType = "video/mp4";
		}
		// SVG images
		elseif(strtolower(substr($filepath, -4))===".svg"){
			$contentType = "image/svg+xml";
		}
		return $contentType;
	}

	/**
	* Small helper to hide folders from sync clients.
	*/
	private function excludePath($path){
		
		// rclone does not deal well with % in filenames. We use rclone for server-server backup.
		// Thus we need this ugly hack.
		// TODO: inform user about the problem.
		if(!empty($_SERVER['HTTP_USER_AGENT']) && stripos($_SERVER['HTTP_USER_AGENT'], "rclone")!==false &&
				stripos($path, "%")!==false &&
				OC_Chooser::checkTrusted($_SERVER['REMOTE_ADDR'])){
					OC_Log::write('chooser','RCLONE PROBLEM - percent in path/filename: '.$path, OC_Log::WARN);
				return true;
		}
		
		//if(stripos($_SERVER['HTTP_USER_AGENT'], "cadaver")===false && stripos($_SERVER['HTTP_USER_AGENT'], "curl")===false){
		if(!isset($_SERVER['HTTP_USER_AGENT']) || strpos($_SERVER['HTTP_USER_AGENT'], "IP_PASS:")===0 ||
				stripos($_SERVER['HTTP_USER_AGENT'], "mirall")===false &&
				stripos($_SERVER['HTTP_USER_AGENT'], "csyncoC")===false /*&&
				stripos($_SERVER['HTTP_USER_AGENT'], "iOs")===false &&
				stripos($_SERVER['HTTP_USER_AGENT'], "Android-ownCloud")===false &&
				stripos($_SERVER['HTTP_USER_AGENT'], "ownCloud-android")===false*/){
			return false;
		}
		if(!\OCP\App::isEnabled('files_sharding')){
			return false;
		}
		// Don't hide Data folders from backup
		if(OC_Chooser::checkTrusted($_SERVER['REMOTE_ADDR'])){
			return false;
		}
		return \OCA\FilesSharding\Lib::inDataFolder($path);
	}

	public function getPropertiesForPath($path, $propertyNames = array(), $depth = 0) {
		
		//	if ($depth!=0) $depth = 1;
		
		$path = rtrim($path,'/');
		
		$returnPropertyList = array();
		
		// Hide the folder completely
		if($this->excludePath($path)){
			return $returnPropertyList;
		}
		
		if($this->mediaSearch){
			$nodes = array();
		}
		elseif($this->favoriteSearch){
			//$parentNode = $this->tree->getNodeForPath($path);
			//$nodes = array($path => $parentNode);
			$nodes = array();
		}
		else{
			$parentNode = $this->tree->getNodeForPath($path);
			$nodes = array($path => $parentNode);
		}
		
		if($this->favoriteSearch){
			$children = $this->tree->getChildren($path);
			foreach($children as $childNode){
				OC_Log::write('chooser','node: '.$path.":".$depth.":".$childNode->getName().":".
						serialize($childNode->getProperties(array('href'))), OC_Log::WARN);
				$nodes[$path . '/' . $childNode->getName()] = $childNode;
			}
		}
		elseif($depth==1 && !empty($parentNode) && $parentNode instanceof \Sabre\DAV\ICollection) {
			$children = $this->tree->getChildren($path);
			foreach($children as $childNode){
				OC_Log::write('chooser','node: '.$path.":".$depth.":".$childNode->getName().":".
						$childNode->getFileId().":".get_class($childNode).":".get_class($parentNode).':'.
						(empty($this->tree->sharingIn)?'':$this->tree->sharingIn), OC_Log::INFO);
				if($this->excludePath($path . '/' . $childNode->getName())){
					continue;
				}
				if($this->mediaSearch && (!$childNode instanceof \OC_Connector_Sabre_File ||
					(substr($childNode->getContentType(), 0, 6)!="image/" &&
							substr($childNode->getContentType(), 0, 6)!="movie/" &&
							substr($childNode->getContentType(), 0, 6)!="video/"))){
					continue;
				}
				$nodes[$path . '/' . $childNode->getName()] = $childNode;
			}
		}
		elseif($this->mediaSearch ||
				$depth == self::DEPTH_INFINITY && $parentNode instanceof \Sabre\DAV\ICollection) {
			$this->addPathNodesRecursively($nodes, $path);
		}
		
		OC_Log::write('chooser','nodes: '.$path.":".$depth.":".count($nodes), OC_Log::INFO);


		// If the propertyNames array is empty, it means all properties are requested.
		// We shouldn't actually return everything we know though, and only return a
		// sensible list.
		$allProperties = count($propertyNames)==0;
		OC_Log::write('chooser','REQUESTED PROPERTIES: '.serialize($propertyNames), OC_Log::INFO);
		
		foreach($nodes as $myPath=>$node) {

			$currentPropertyNames = $propertyNames;

			$newProperties = array(
				'200' => array(),
				'404' => array(),
			);

			if ($allProperties) {
				// Default list of propertyNames, when all properties were requested.
				$currentPropertyNames = array(
					'{DAV:}getlastmodified',
					'{DAV:}getcontentlength',
					'{DAV:}resourcetype',
					'{DAV:}quota-used-bytes',
					'{DAV:}quota-available-bytes',
					'{DAV:}getetag',
					'{DAV:}getcontenttype',
				);
			}

			// If the resourceType was not part of the list, we manually add it
			// and mark it for removal. We need to know the resourcetype in order
			// to make certain decisions about the entry.
			// WebDAV dictates we should add a / and the end of href's for collections
			$removeRT = false;
			if (!in_array('{DAV:}resourcetype',$currentPropertyNames)) {
				$currentPropertyNames[] = '{DAV:}resourcetype';
				$removeRT = true;
			}
			
			if($node instanceof \OC_Connector_Sabre_Sharingout_Directory ||
				$node instanceof \OC_Connector_Sabre_Favorites_Directory){
				$result = true;
			}
			// infuse support: infuse cannot parse XML with OC properties.
			// Unfortunately infuse does not provide a user agent,
			// so we can only try to recognize it by user-Agent not being set...
			elseif($node instanceof \OC_Connector_Sabre_Sharingin_Directory &&
					!(empty($_SERVER['HTTP_USER_AGENT']) && !$allProperties)){
				// beforeGetProperties() calls $node->getFileId() and $node->getDavPermissions()
				// which we override in sharingin_directory.php
				$result = $this->broadcastEvent('beforeGetProperties',array($myPath, $node, &$currentPropertyNames, &$newProperties));
			}
			elseif(!(empty($_SERVER['HTTP_USER_AGENT']) && !$allProperties)){
				$result = $this->broadcastEvent('beforeGetProperties',array($myPath, $node, &$currentPropertyNames, &$newProperties));
			}
			
			// If this method explicitly returned false, we must ignore this
			// node as it is inaccessible.
			if ($result===false) continue;

			if (count($currentPropertyNames) > 0) {

				if ($node instanceof \Sabre\DAV\IProperties) {
					$nodeProperties = $node->getProperties($currentPropertyNames);

					// The getProperties method may give us too much,
					// properties, in case the implementor was lazy.
					//
					// So as we loop through this list, we will only take the
					// properties that were actually requested and discard the
					// rest.
					foreach($currentPropertyNames as $k=>$currentPropertyName) {
						if (isset($nodeProperties[$currentPropertyName])) {
							unset($currentPropertyNames[$k]);
							$newProperties[200][$currentPropertyName] = $nodeProperties[$currentPropertyName];
						}
					}

				}

			}

			foreach($currentPropertyNames as $prop) {
				OC_Log::write('chooser','PROP: '.$myPath.'-->'.$prop, OC_Log::INFO);
				if(isset($newProperties[200][$prop])){
					continue;
				}
				switch($prop) {
					case '{DAV:}getlastmodified':
						if (!empty($node->getLastModified())){
							$newProperties[200][$prop] =
							new \Sabre\DAV\Property\GetLastModified($node->getLastModified());
						}
						break;
					case '{DAV:}displayname':
						if (!empty($myPath)){
							$newProperties[200][$prop] = $myPath=='/'?'/':basename($myPath);
						}
						break;
					case '{DAV:}getcontentlength':
						if ($node instanceof \Sabre\DAV\IFile) {
							$size = $node->getSize();
							if (!is_null($size)) {
								$newProperties[200][$prop] = (int)$node->getSize();
							}
						}
						break;
					case '{DAV:}quota-used-bytes':
						if ($node instanceof \Sabre\DAV\IQuota) {
							$quotaInfo = $node->getQuotaInfo();
							$newProperties[200][$prop] = $quotaInfo[0];
						}
						break;
					case '{DAV:}quota-available-bytes':
						if ($node instanceof \Sabre\DAV\IQuota) {
							$quotaInfo = $node->getQuotaInfo();
							$newProperties[200][$prop] = $quotaInfo[1];
						}
						break;
					case '{DAV:}getetag':
						if($node instanceof \Sabre\DAV\IFile && $etag = $node->getETag()){
							$newProperties[200][$prop] = $etag;
						}
						break;
					case '{DAV:}getcontenttype':
						if($node instanceof \Sabre\DAV\IFile && $ct = self::unsecureContentType($myPath, $node->getContentType())){
							$newProperties[200][$prop] = $ct;
						}
						break;
					case '{DAV:}supported-report-set':
						$reports = array();
						foreach($this->plugins as $plugin) {
							$reports = array_merge($reports, $plugin->getSupportedReportSet($myPath));
						}
						$newProperties[200][$prop] = new \Sabre\DAV\Property\SupportedReportSet($reports);
						break;
					case '{DAV:}resourcetype':
						$newProperties[200]['{DAV:}resourcetype'] = new \Sabre\DAV\Property\ResourceType();
						foreach($this->resourceTypeMapping as $className => $resourceType) {
							if ($node instanceof $className) $newProperties[200]['{DAV:}resourcetype']->add($resourceType);
						}
						break;
					case '{' . self::NS_NEXTCLOUD . '}has-preview':
						if($node instanceof \Sabre\DAV\IFile){
							if($node->getContentType()=="application/pdf" ||
									substr($node->getContentType(), 0, 6)=="image/" ||
									substr($node->getContentType(), 0, 6)=="movie/" ||
									substr($node->getContentType(), 0, 6)=="video/" ||
									substr($node->getContentType(), 0, 5)=="text/"){
								$newProperties[200][$prop] = true;
							}
							OC_Log::write('chooser','PROP: '.$myPath.'-->'.$prop.'-->'.get_class($node).
									'-->'.$node->getContentType().'-->'.$newProperties[200][$prop], OC_Log::INFO);
						}
						else{
						}
						break;
					case '{' . self::NS_NEXTCLOUD . '}creation_time':
						if ($node->getLastModified()){
							$newProperties[200][$prop] = $node->getLastModified();
						}
						break;
						//case '{' . \OC_Connector_Sabre_FilesPlugin::NS_OWNCLOUD . '}favorite' :
						//OC_Log::write('chooser','FAVORITE REQUEST --> '.$myPath, OC_Log::WARN);
						// We don't care about group folders or shared files as these
						// are anyway not accessible to the sync clients.
						// Not necessary - we set the property when setting bookmark (and vice versa)
						/*if(\OCP\App::isEnabled('internal_bookmarks')){
							require_once 'apps/internal_bookmarks/lib/intbks.class.php';
							$bookmark = \OC_IntBks::getItemByTarget('/'.rtrim($myPath, '/'));
							if(!empty($bookmark)){
								$newProperties[200][$prop] = 1;
							}
						}*/
						//break;

				}

				// If we were unable to find the property, we will list it as 404.
				if(!$allProperties && !isset($newProperties[200][$prop])){
					$newProperties[404][$prop] = null;
				}

			}

			if($node instanceof \OC_Connector_Sabre_Sharingin_Directory ||
					$node instanceof \OC_Connector_Sabre_Sharingout_Directory){
				$result = true;
			}
			else{
				$this->broadcastEvent('afterGetProperties',array(trim($myPath,'/'),&$newProperties, $node));
			}

			if($node instanceof \OC_Connector_Sabre_Favorite_Directory){
				$newProperties['href'] = trim($node->getHref(),'/');
			}
			else{
				$newProperties['href'] = trim($myPath,'/');
			}

			// Its is a WebDAV recommendation to add a trailing slash to collectionnames.
			// Apple's iCal also requires a trailing slash for principals (rfc 3744), though this is non-standard.
			if ($myPath!='' && isset($newProperties[200]['{DAV:}resourcetype'])) {
				$rt = $newProperties[200]['{DAV:}resourcetype'];
				if ($rt->is('{DAV:}collection') || $rt->is('{DAV:}principal')) {
					$newProperties['href'] .='/';
				}
			}

			if($this->mediaSearch){
				$manualProp = '{' . \OC_Connector_Sabre_FilesPlugin::NS_OWNCLOUD . '}fileid';
				$newProperties[200][$manualProp] = \OCA\FilesSharding\Lib::getFileId($path);
				unset($newProperties[404][$manualProp]);
				$manualProp = '{' . \OC_Connector_Sabre_FilesPlugin::NS_OWNCLOUD . '}size';
				$newProperties[200][$manualProp] =
				$node instanceof \Sabre\DAV\IFile ? $node->getSize() : 0;
				unset($newProperties[404][$manualProp]);
				$manualProp = '{DAV:}getcontentlength';
				$newProperties[200][$manualProp] =
				$node instanceof \Sabre\DAV\IFile ? $node->getSize() : 0;
				unset($newProperties[404][$manualProp]);
				$manualProp = '{' . \OC_Connector_Sabre_FilesPlugin::NS_OWNCLOUD . '}favorite';
				$newProperties[200][$manualProp] = '0';
				unset($newProperties[404][$manualProp]);
				$manualProp = '{' . self::NS_NEXTCLOUD . '}is-encrypted';
				$newProperties[200][$manualProp] = '0';
				unset($newProperties[404][$manualProp]);
				//manualProp = '{' . self::NS_NEXTCLOUD . '}has-preview';
				//$newProperties[200][manualProp] = '0';
				if(!empty($this->where)){
					$whereExp = $this->where;
					foreach(array_keys($newProperties[200]) as $prop){
						$val = $newProperties[200][$prop];
						if($prop=='{DAV:}getlastmodified'){
							$time = $val->getTime();
							$time->setTimeZone(new DateTimeZone('Europe/Copenhagen'));
							$val = $time->format('Y-m-d').'T'.$time->format('H:i:sP');
						}
						$whereExp = str_replace($prop, $val, $whereExp);
					}
					$whereExp = preg_replace('| ([^ ]+) like ([^ ]+)/% |', ' $1 like $2/.* ', $whereExp);
					$whereExp = preg_replace('| ([^ ]+) like ([^ ]+) |', ' preg_match("|$2|", "$1") ', $whereExp);
					$whereExp = preg_replace('| ([^ ]+) lt ([^ ]+) |', ' "$1" < "$2" ', $whereExp);
					$whereExp = preg_replace('| ([^ ]+) gt ([^ ]+) |', ' "$1" > "$2" ', $whereExp);
					$whereExp = '$res = ' . $whereExp . '; return $res;';
					$res = eval($whereExp);
					OC_Log::write('chooser','Where expression eval res: '.$whereExp.'-->'.$res, OC_Log::INFO);
					if(!$res){
						continue;
					}
				}

			}
			
			elseif($this->favoriteSearch){
				$manualProp = '{' . \OC_Connector_Sabre_FilesPlugin::NS_OWNCLOUD . '}size';
				$newProperties[200][$manualProp] =
					$node instanceof \Sabre\DAV\IFile ? $node->getSize() : 0;
				unset($newProperties[404][$manualProp]);
				$manualProp = '{DAV:}getcontentlength';
				$newProperties[200][$manualProp] =
					$node instanceof \Sabre\DAV\IFile ? $node->getSize() : 0;
				unset($newProperties[404][$manualProp]);
				$manualProp = '{' . \OC_Connector_Sabre_FilesPlugin::NS_OWNCLOUD . '}favorite';
				$newProperties[200][$manualProp] = $this->favoriteSearch?'1':'0';
				unset($newProperties[404][$manualProp]);
				$manualProp = '{' . self::NS_NEXTCLOUD . '}is-encrypted';
				$newProperties[200][$manualProp] = '0';
				unset($newProperties[404][$manualProp]);
				if($node instanceof OC_Connector_Sabre_Favorite_Directory){
					$manualProp = '{' . \OC_Connector_Sabre_FilesPlugin::NS_OWNCLOUD . '}fileid';
					$newProperties[200][$manualProp] = $node->getOcFileId();
					unset($newProperties[404][$manualProp]);
				}
			}
			else{
				if($node instanceof \OC_Connector_Sabre_Sharingin_Directory || $node instanceof \OC_Connector_Sabre_Sharingout_Directory){
					// We're in top-level /sharingin/
							$manualProp = '{' . \OC_Connector_Sabre_FilesPlugin::NS_OWNCLOUD . '}fileid';
							// This will return a non-integer, but unique fileid
							$newProperties[200][$manualProp] = $node->getFileId();
							unset($newProperties[404][$manualProp]);
				}
				elseif(!empty($this->tree->sharingIn) && $this->tree->sharingIn){
					// When $myPath is /user_id/shared_folder and we're in /sharingin,
					// we cannot get the fileid of the shared_folder, so we generate a unique one,
					// based on the the path
					$manualProp = '{' . \OC_Connector_Sabre_FilesPlugin::NS_OWNCLOUD . '}fileid';
					$newProperties[200][$manualProp] = substr(md5($myPath), 0, 21);
					unset($newProperties[404][$manualProp]);
				}
				elseif(!empty($this->tree->sharingOut) && $this->tree->sharingOut){
					// When $myPath is /user_id/shared_folder and we're in /sharingout,
					// we _can_ get the fileid of the shared_folder.
					// Again, a non-integer, but unique fileid
					$manualProp = '{' . \OC_Connector_Sabre_FilesPlugin::NS_OWNCLOUD . '}fileid';
					/*$myNode = $this->tree->getNodeForPath($myPath);
					\OC_Log::write('chooser','Getting fileid for '.$myPath.':'.$path.':'.get_class($myNode).':'.
							':'.$myNode->getFileId().':'.$myNode->getName(), \OC_Log::WARN);
					$newProperties[200][$manualProp] = $myNode->getFileId();*/
					$newProperties[200][$manualProp] = $node->getFileId();
					unset($newProperties[404][$manualProp]);
				}
				else{
						// this is the standard case
						$manualProp = '{' . \OC_Connector_Sabre_FilesPlugin::NS_OWNCLOUD . '}fileid';
						$newProperties[200][$manualProp] = \OCA\FilesSharding\Lib::getFileId($myPath);
						unset($newProperties[404][$manualProp]);
				}
			}

			// If the resourcetype property was manually added to the requested property list,
			// we will remove it again.
			if($removeRT){
				unset($newProperties[200]['{DAV:}resourcetype']);
			}

			$returnPropertyList[] = $newProperties;

		}

		OC_Log::write('chooser','Properties: '.serialize($returnPropertyList), OC_Log::INFO);
		
		return $returnPropertyList;

	}
	
	public function broadcastEvent($eventName, $arguments = array()) {
		if(isset($this->eventSubscriptions[$eventName])) {
			foreach($this->eventSubscriptions[$eventName] as $subscriber) {
				if($eventName=='afterWriteContent' || $eventName=='afterCreateFile'){
					$this->tree->fixPath($arguments[0]);
					OC_Log::write('chooser', 'Fixed path: '.$eventName." -> ".$arguments[0], OC_Log::DEBUG);
				}
				$result = call_user_func_array($subscriber, $arguments);
				if ($result===false) return false;
			}
		}
		return true;
  }
	
}
