<?php
    class FileUploader{
        const KB=1024;
        const MB=1024*1024;
        private $blockSize=0;
        private $onProcess;
        private $filesPath;
        private $uploadsPath;
        private $key;
        public function __construct($blockSize,$filesPath,$uploadsPath,$key,$onProcess){
            $maxSize1=$this->convertPHPSizeToBytes(ini_get('upload_max_filesize'));
            $maxSize2=$this->convertPHPSizeToBytes(ini_get('post_max_size'));
            $maxSize=min($maxSize1,$maxSize2)-1*self::MB;
            if($blockSize>$maxSize){
                $blockSize=$maxSize;
            }
            $this->filesPath=$filesPath;
            $this->uploadsPath=$uploadsPath;
            $this->blockSize=$blockSize;
            $this->key=$key;
            $this->onProcess=$onProcess;
            $this->run();
        }
        private function getBlockSize(){
            return $this->blockSize;
        }
        private function getKey(){
            return $this->key;
        }
        private function getPath(){
            return $this->filesPath;
        }
        private function getUploadsPath(){
            return $this->uploadsPath;
        }
        public static function reject(){
            ob_clean();
            echo json_encode(["q" => 'File rejected']);
            ob_flush();
            exit();
        }
        private function convertPHPSizeToBytes($sSize)
        {
            $sSuffix = strtoupper(substr($sSize, -1));
            if (!in_array($sSuffix,array('P','T','G','M','K'))){
                return (int)$sSize;  
            } 
            $iValue = substr($sSize, 0, -1);
            switch ($sSuffix) {
                case 'P':
                    $iValue *= 1024;
                case 'T':
                    $iValue *= 1024;
                case 'G':
                    $iValue *= 1024;
                case 'M':
                    $iValue *= 1024;
                case 'K':
                    $iValue *= 1024;
                    break;
            }
            return intval($iValue);
        }      
        private function removeDirectory($path) {
            $files = glob($path . '/*');
           foreach ($files as $file) {
               is_dir($file) ? removeDirectory($file) : unlink($file);
           }
           rmdir($path);
           return;
       }
       private function CreatFileDummy($file_name,$size) {
           $f = fopen($file_name, 'wb');
           if($size >= 1000000000)  {
               $z = ($size / 1000000000);        
               if (is_float($z))  { 
                   $z = round($z,0);
                   fseek($f, ( $size - ($z * 1000000000) -1 ), SEEK_END);
                   fwrite($f, "\0");
               }        
               while(--$z > -1) {
                   fseek($f, 999999999, SEEK_END);
                   fwrite($f, "\0");
               }
           } 
           else {
               fseek($f, $size - 1, SEEK_END);
               fwrite($f, "\0");
           }
           fclose($f);
   
           return true;
       }
       private function run(){
            ob_clean();
            $fu=$this;
            $response=array();
            switch($_POST['o']){
                case 0:
                    $files = glob($fu->getPath() . '/*');
                    foreach ($files as $file) {
                        if(is_dir($file)){
                            $stat = stat($file);
                            if(call_user_func($this->onProcess,array(
                                'operation' => 'CLEAN_TEMP_FILES',
                                'file' => $file,
                                'time' => time()-$stat['ctime']
                            ))){
                                $this->removeDirectory($file);
                            }
                        }
                    }
                    $response['b']=$fu->getBlockSize();
                    $response['q']='';
                break;
                case 1:
                    $file_id=hash_hmac('sha1',$_POST['i'],$fu->getKey());
                    call_user_func($fu->onProcess,array(
                        'operation' => 'FILE_UPLOAD_REQUEST',
                        'file_id' => $file_id,
                        'name' => $_POST['n'],
                        'size' => $_POST['s'],
                        'type' => $_POST['t'],
                        'blockSize' => $fu->getBlockSize()
                    ));
                    if(!file_exists($fu->getPath().'/'.$file_id)){
                        mkdir($fu->getPath().'/'.$file_id);
                        file_put_contents($fu->getPath().'/'.$file_id.'/fileinfo',serialize(array(
                            'name' => $_POST['n'],
                            'size' => $_POST['s'],
                            'type' => $_POST['t'],
                            'blockSize' => $fu->getBlockSize(),
                            'blocks' => 0
                        )));
                        $fu->CreatFileDummy($fu->getPath().'/'.$file_id.'/file',$_POST['s']);
                    }
                    $file_info=unserialize(file_get_contents($fu->getPath().'/'.$file_id.'/fileinfo'));
                    $response['b']=$file_info['blockSize'];
                    $response['l']=($file_info['blocks']);
                    $response['q']='';
                break;
                case 2:
                    $part=intval($_POST['p']);
                    if(is_integer($part)){
                        if($part>=0){
                            $file_id=hash_hmac('sha1',$_POST['i'],$fu->getKey());
                            if(file_exists($fu->getPath().'/'.$file_id)){
                                $file_info=unserialize(file_get_contents($fu->getPath().'/'.$file_id.'/fileinfo'));
                                if($part<=floor($file_info['size']/$file_info['blockSize'])){
                                    $file_info['blocks']++;
                                    call_user_func($fu->onProcess,array(
                                        'operation' => 'FILE_BLOCK_UPLOAD_REQUEST',
                                        'block_id' => $part,
                                        'file_id' => $file_id,
                                        'name' => $file_info['name'],
                                        'size' => $file_info['size'],
                                        'type' => $file_info['type'],
                                        'blockSize' => $file_info['blockSize'],
                                    ));
                                    file_put_contents($fu->getPath().'/'.$file_id.'/fileinfo',serialize($file_info));
                                    $file=fopen($fu->getPath().'/'.$file_id.'/file','r+b');
                                    $file2=fopen($_FILES['d']['tmp_name'],'r');
                                    fseek($file,$part*$fu->getBlockSize());
                                    fwrite($file,fread($file2,$_FILES['d']['size']));
                                    fclose($file2);
                                    fclose($file);
                                    unlink($_FILES['d']['tmp_name']);
                                }
                            }
                        }
                    }
                    $response['q']='';
                break;
                case 3:
                    $file_id=hash_hmac('sha1',$_POST['i'],$fu->getKey());
                    if(file_exists($fu->getPath().'/'.$file_id)){
                        $file_info=unserialize(file_get_contents($fu->getPath().'/'.$file_id.'/fileinfo'));
                        $newName=call_user_func($fu->onProcess,array(
                            'operation' => 'FILE_UPLOAD_END_REQUEST',
                            'file_id' => $file_id,
                            'name' => $file_info['name'],
                            'size' => $file_info['size'],
                            'type' => $file_info['type'],
                            'blockSize' => $file_info['blockSize'],
                        ));
                        $response['s']=true;
                        if($response['s']){
                            if($newName==null){
                                $newName=$file_info['name'];
                            }
                            rename($fu->getPath().'/'.$file_id.'/file',$fu->getUploadsPath().'/'.$newName);
                            $fu->removeDirectory($fu->getPath().'/'.$file_id);
                        }
                        call_user_func($fu->onProcess,array(
                            'operation' => 'FILE_UPLOAD_END',
                            'file_id' => $file_id,
                            'name' => $file_info['name'],
                            'size' => $file_info['size'],
                            'type' => $file_info['type'],
                            'blockSize' => $file_info['blockSize'],
                        ));
                    }
                    $response['q']='';
                break;
            }
            echo json_encode($response);
            ob_flush();
       }
    }
?>