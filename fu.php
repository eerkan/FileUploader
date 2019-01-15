<?php
    ini_set('max_execution_time','3000');
    require_once("fileuploader.php");
    $upload_dir='uploads/temp_files';
    $fu=new FileUploader(1*FileUploader::MB,$upload_dir,'uploads','key',
        function($upload){
            switch($upload['operation']){
                case 'FILE_UPLOAD_REQUEST':
                    /*if (strpos($upload['name'], '') === false) {
                        FileUploader::reject();
                    }*/
                break;
                case 'FILE_BLOCK_UPLOAD_REQUEST':

                break;
                case 'FILE_UPLOAD_END_REQUEST':
                    return md5(rand(0,0xffffffff)).$upload['name'];
                break;
                case 'FILE_UPLOAD_END':

                break;
                case 'CLEAN_TEMP_FILES':
                    return $upload['time']>60*60*6;
                break;
            }
        }
    );
?>
