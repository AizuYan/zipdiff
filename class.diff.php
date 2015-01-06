<?php
/**
 * zip压缩文件增量修改文件导出
 * 
 * @author 燕睿涛(luluyrt@163.com)
 *
 * @time 2015年1月6日 22:09:40
 *
 */
class Diff{
	/**
	 * @var array 错误信息常量
	 *
	 */
	 private static $_ERROR = array(
	    'ER_MULTIDISK' => 'Multi-disk zip archives not supported.',
	    'ER_RENAME' => 'Renaming temporary file failed.',
	    'ER_CLOSE' => 'Closing zip archive failed', 
	    'ER_SEEK' => 'Seek error',
	    'ER_READ' => 'Read error',
	    'ER_WRITE' => 'Write error',
	    'ER_CRC' => 'CRC error',
	    'ER_ZIPCLOSED' => 'Containing zip archive was closed',
	    'ER_NOENT' => 'No such file.',
	    'ER_EXISTS' => 'File already exists',
	    'ER_OPEN' => 'Can\'t open file', 
	    'ER_TMPOPEN' => 'Failure to create temporary file.',
	    'ER_ZLIB' => 'Zlib error',
	    'ER_MEMORY' => 'Memory allocation failure', 
	    'ER_CHANGED' => 'Entry has been changed',
	    'ER_COMPNOTSUPP' => 'Compression method not supported.', 
	    'ER_EOF' => 'Premature EOF',
	    'ER_INVAL' => 'Invalid argument',
	    'ER_NOZIP' => 'Not a zip archive',
	    'ER_INTERNAL' => 'Internal error',
	    'ER_INCONS' => 'Zip archive inconsistent', 
	    'ER_REMOVE' => 'Can\'t remove file',
	    'ER_DELETED' => 'Entry has been deleted',
	  );	
	/**
	 * @var string 保存文件的路径
	 *
	 */
	private $_file = null;

	/**
	 * @var string 保存打开文件的句柄
	 *
	 */
	private $_zip = null;

	/**
	 * @var string Diff对象的实例
	 *
	 */
	private static $_instance = array();

	public static function instance($file){
		if(!file_exists($file)){
			echo self::$_ERROR['ER_NOENT'];
			return false;
		}
		if(!self::isZip($file)){
			echo self::$_ERROR['ER_NOZIP'];
			return false;
		}
		$index = self::pathIndex($file);
		if(!isset(self::$_instance[$index]) || !is_object(self::$_instance[$index])){
			self::$_instance[$index] = new self($file);
		}
		return self::$_instance[$index];
	}

	public function __construct($file){
		$this->_file 	= $file;
		$this->_zip 	= zip_open($file);
	}

	/**
	 * @desc 获取该压缩文件中的 所有 文件名=>文件md5 值
	 *
	 * @return array
	 */
	public function hashAll(){
		$hash = array();
		while(($zip_entry = zip_read($this->_zip)) !== false){
			$name = zip_entry_name($zip_entry);
			$name = self::pathIndex($name);
			zip_entry_open($this->_zip,$zip_entry);
			$hash[$name] = md5(zip_entry_read($zip_entry,zip_entry_filesize($zip_entry)));
		}
		return $hash;
	}


	/**
	 * @desc 获取该压缩文件中的 一个 文件名=>文件md5 值
	 *
	 * @return array
	 */
	public function hashOne(){
		$ret = array();
		$zip_entry = zip_read($this->_zip);
		if($zip_entry){
			$name = zip_entry_name($zip_entry);
			$name = self::pathIndex($name);
			zip_entry_open($this->_zip,$zip_entry);
			$ret['name'] = $name;
			$ret['content'] = zip_entry_read($zip_entry,zip_entry_filesize($zip_entry));
			$ret['value'] = md5($ret['content']);
			return $ret;
		}
		return false;
	}

	/**
	 * 
	 * @desc 寻找增量更新（当前类中的压缩文件相对于$old添加的文件和修改的文件）
	 * 
	 * @param $lod Diff 一个要比较的类
	 *
	 * @param $pathRoot 要输出的文件夹
	 *
	 * @param $isZip blooean 是否生成压缩文件，生成则删除$pathRoot文件夹
	 */
	public function diff(Diff $old, $pathRoot=null, $isZip=false){
		$_ret = array('add'=>array(),'edit'=>array(),'common'=>array());
		if($pathRoot != null && !is_dir($pathRoot)){
			if(!@mkdir($pathRoot,0777,true)){
				echo "请创建好文件夹";
				return;
			}
		}
		$pathRoot = realpath($pathRoot);
		$hashOld = $old->hashAll();
		$new = true;
		while ($new !== false) {
			$new = $this->hashOne();
			if($new === false) break;
			$name = $new['name'];
			$value = $new['value'];
			$path = self::unPathIndex($name);
			if(isset($hashOld[$name])){
				if($hashOld[$name] != $value){
					array_push($_ret['edit'],$path);
					if($pathRoot != null){
						$path = $pathRoot.DIRECTORY_SEPARATOR.$path;
						self::createFile($path,$new['content']);
					}
				}else{
					array_push($_ret['common'],$path);
				}
			}else{
				array_push($_ret['add'], $path);
				if($pathRoot != null){
					$path = $pathRoot.DIRECTORY_SEPARATOR.$path;
					self::createFile($path,$new['content']);
				}
			}
		}
		if($isZip && PHP_OS == 'Linux'){
			shell_exec("cd {$pathRoot}".DIRECTORY_SEPARATOR.";zip -r /tmp/change.zip ./*;");
			shell_exec("rm -rf {$pathRoot}".DIRECTORY_SEPARATOR."*;");
			shell_exec("mv /tmp/change.zip {$pathRoot}".DIRECTORY_SEPARATOR.";");
		}
		return true;
	}

	public function __destruct(){
	}

	
	//删除一个对象并释放资源
	public function del(){
		$index = self::pathIndex($this->_file);
		if(isset(self::$_instance[$index])){
			zip_close(self::$_instance[$index]->_zip);
			unset(self::$_instance[$index]);
		}
		return true;
	}

	/**
	 * 判断是否是zip文件
	 */
	public static function isZip($file){
		return substr($file, -4) == '.zip' ? true : false;
	}

	/**
	 * 把路径转换为数组的索引
	 * @param string $file 要转换为字符串的路径
	 */
	public static function pathIndex($file){
		return str_replace(array('.','\\','/'), array(chr(8),chr(9),chr(9)), $file);
	}

	/**
	 * 把通过pathIndex获得的索引转换为路径
	 * @param string $string 要转换为路径的字符串
	 */
	public static function unPathIndex($string){
		return str_replace(array(chr(8),chr(9)), array('.',DIRECTORY_SEPARATOR), $string);
	}


	/**
	 * 写入差异文件
	 * @param $path  string 文件或者路径
	 * @param $content mixed 要写入的文件内容
	 */
	public static function createFile($path,$content){
		if(self::isDirString($path)){
		    if(!is_dir($path) && mkdir($path,0777,true)){
		     	return;
		     }
		}
		!is_dir(dirname($path)) && mkdir(dirname($path), 0777, true);
		$fp = @fopen($path, "w+");
		if(!$fp){
			return;
		}
		fwrite($fp, $content);
		fclose($fp);
	}

	/**
	 * 判断一个路径是不是一个文件件
	 * @param $string string 路径
	 * @return true/false
	 */
	public static function isDirString($string){
		return substr($string, -1) == DIRECTORY_SEPARATOR ? true : false;
	}
}
$a = microtime(true);
$new = Diff::instance("./laraveladd.zip");
$old = Diff::instance("./laravel.zip");
$new->diff($old,'./result',true);
$b = microtime(true);
echo "花费时间：".($b-$a)."s\n";
echo (memory_get_peak_usage()/1024/1024)."M\n";
