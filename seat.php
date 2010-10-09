<?php
	
	/**
	 *	Seat-PHP (0.1)
	 *	PHP CouchDB Wrapper
	 *	http://github.com/stackd/seat-php	
	 *
	 *	DOCUMENTATION
	 *	----------------------------------------
	 *	see README.md
	 *	
	 *	LICENSE
	 *	----------------------------------------
	 *	MIT, see LICENSE
	 *
	 *	DEPENDENCIES
	 *	----------------------------------------
	 *	$ sudo pear install Net_URL2-0.3.1
	 *	$ sudo pear install HTTP_Request2-0.5.2
	 */

	require_once 'HTTP/Request2.php';
	
	class Seat {
		
		const DEFAULT_HOST = 'localhost';
		const DEFAULT_PORT = 5984;
		const USER_AGENT = 'Seat-PHP (0.1)';
		
		protected $host;
		protected $port;
		protected $db;
		protected $user;
		protected $pass;
		protected $design_doc;
		
		public function get ($docid='') {
			$url = "http://".$this->host.
						":".(string)$this->port.
						"/".$this->db.
						"/".(string)$docid;
			$req  = new HTTP_Request2($url);
			$req  = $req->setMethod(HTTP_Request2::METHOD_GET)
						->setHeader(array(
								'User-Agent' => self::USER_AGENT
							));
			if (isset($this->user) && isset($this->pass)) {
				$req = $req->setAuth($this->user, $this->pass);
			}
			$resp = $req->send();
			return json_decode($resp->getBody());
		}
		
		public function post ($path='') {
			$url = "http://".$this->host.
						":".(string)$this->port.
						"/".$this->db.
						"/".(string)$path;
			$req  = new HTTP_Request2($url);
			$req  = $req->setMethod(HTTP_Request2::METHOD_POST)
						->setHeader(array(
								'Content-Type' => 'application/json',
								'User-Agent' => self::USER_AGENT
							));
			if (isset($this->user) && isset($this->pass)) {
				$req = $req->setAuth($this->user, $this->pass);
			}
			$resp = $req->send();			
			return json_decode($resp->getBody());
		}
		
		public function put ($doc=array()) {
			$doc = (array)$doc;
			$url = "http://".$this->host.
						":".(string)$this->port.
						"/".$this->db.
						"/".$doc['_id'];
			unset($doc['_id']);
			$json = json_encode($doc);
			$req  = new HTTP_Request2($url);
			$req  = $req->setMethod(HTTP_Request2::METHOD_PUT)
						->setHeader(array(
								'Content-Type' => 'application/json',
								'User-Agent' => self::USER_AGENT
							))
						->setBody($json);
			if (isset($this->user) && isset($this->pass)) {
				$req = $req->setAuth($this->user, $this->pass);
			}
			$resp = $req->send();			
			return json_decode($resp->getBody());			
		}
		
		// destroy doc in db
		public function delete ($doc=false) {
			$doc = (array)$doc;
			$url = "http://".$this->host.
						":".(string)$this->port.
						"/".$this->db.
						"/".$doc['_id'].
						"?rev=".$doc['_rev'];
			$req  = new HTTP_Request2($url);
			$req  = $req->setMethod(HTTP_Request2::METHOD_DELETE)
						->setHeader(array(
								'User-Agent' => self::USER_AGENT
							));
			if (isset($this->user) && isset($this->pass)) {
				$req = $req->setAuth($this->user, $this->pass);
			}
			$resp = $req->send();
			return json_decode($resp->getBody());
		}
		
		public function pushViews () {
			$dirs = glob('./views/*/*/*');
			$updated_views = array();
			foreach ($dirs as $dir) {
				$split = explode('/', $dir);
				$db = $split[2];
				$design_doc = $split[3];
				$view = $split[4];
				if (file_exists('./views/'.$db.'/'.$design_doc.'/'.$view.'/map.js')) {
					$doc = $this->get('_design/'.$design_doc);
					if ($doc->error && $doc->reason=="missing") {
						// doc doesn't exist yet, create
						$doc = array(
								"_id"=>"_design/".$design_doc,
								"language"=>"javascript",
								"views" => array(
										$view=>null
									)
							);
						$doc["views"] = (object)$doc["views"];
						$doc = (object)$doc;
					}
					$update = false;
					// map
					if ($doc->views->$view->map != file_get_contents('./views/'.$db.'/'.$design_doc.'/'.$view.'/map.js')) {
						$doc->views->$view->map = file_get_contents('./views/'.$db.'/'.$design_doc.'/'.$view.'/map.js');
						$update = true;
					}
					// reduce
					if (file_exists('./views/'.$db.'/'.$design_doc.'/'.$view.'/reduce.js') && ($doc->views->$view->reduce != file_get_contents('./views/'.$db.'/'.$design_doc.'/'.$view.'/reduce.js'))) {
						$doc->views->$view->reduce = file_get_contents('./views/'.$db.'/'.$design_doc.'/'.$view.'/reduce.js');
						$update = true;
					}
					if ($update) {
						$this->put($doc);
						$updated_views[] = $doc;
					}
				} else {
					echo 'Error: map.js does not exist in ./views/'.$db.'/'.$design_doc.'/'.$view.'/';
				}
			}
			return $updated_views;
		}
		
		function __construct ($url=null, $user=null, $pass=null) {
			$url = parse_url($url);
			if (strlen($url['host']) > 0) {
				$this->host = $url['host'];
			} else {
				$this->host = self::DEFAULT_HOST;
			}
			if (strlen($url['port']) > 0) {
				$this->port = $url['port'];
			} else {
				$this->port = self::DEFAULT_PORT;
			}
			if (strlen($url['path']) > 1) {
				$path = explode('/', $url['path']);
				foreach ($path as $p) {
					if (strlen($p)>0) {
						$this->db = $p;
						break;
					}
				}
			} else {
				$this->db = false;
			}
			if (isset($url['user'])) { $this->user = $url['user']; }
			if (isset($url['pass'])) { $this->pass = $url['pass']; }
			if (isset($user)) { $this->user = $user; }
			if (isset($pass)) { $this->pass = $pass; }
		}
		
		// sets design document to use when calling a view
		function __get ($design_doc) {
			$this->design_doc = $design_doc;
			return $this;
		}
		
		// GETs a view
		function __call ($name, $args) {
			if (is_array($args[0])) {
				$params = '';
				$i = 0;
				foreach ($args[0] as $k => $v) {
					if ($i!=0) {
						$params.="&";
					}
					$params.="$k=$v";
					$i++;
				}
			} else {
				$params = $args[0];
			}
			$view_url = "_design/".$this->design_doc.
						"/_view/".$name.
						"?".$params;
			return $this->get($view_url);
		}
	}
		
?>