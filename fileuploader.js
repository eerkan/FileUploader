class FileUploader{

    constructor(scriptUrl,fileInput){
        this.fileInput=fileInput;
        this.scriptUrl=scriptUrl;
        this.blockSize=1024*128;
        this.file=null;
        this.fileId=null;
        this.reader=null;
        this.blockList=null;
        this.blockCount=0;
        this.part=0;
        this.maxUploadedBlocks=0;
        this.successfulFunction=() => {};
        this.errorFunction=() => {};
        this.progressFunction=() => {};

        let data=new FormData();
        data.append('o','0');
        let response = fetch(this.scriptUrl,{
            method: 'POST',
            body: data
        })
        .then((response) => {
            return response.json();
        })
        .then((result) => {
            this.blockSize=result['b'];
        });
        
        fileInput.addEventListener('change',(e) => {
            this.file=e.target.files[0];
        });
    }

    upload(){
        let file_id=this.file.name+this.file.type+this.file.size.toString()+this.file.lastModified.toString();
        this.fileId=file_id;
        let data=new FormData();
        data.append('o','1');
        data.append('i',file_id);
        data.append('n',this.file.name);
        data.append('s',this.file.size);
        data.append('t',this.file.type);
        let response = fetch(this.scriptUrl,{
            method: 'POST',
            body: data
        })
        .then((response) => {
            return response.json();
        })
        .then((result) => {
            
            if(result['q']=='File rejected'){
                throw new Error('File rejected');
                return;
            }
            
            this.blockSize=result['b'];
            this.blockList=Array.apply(null, {length: result['l']}).map(Number.call, Number);
            this.reader=new FileReader();
            this.reader.addEventListener('load',(e) => {

                let block_crc32='';
                let blob=new Blob([new Uint8Array(this.reader.result)]);
                let data=new FormData();
                data.append('d',blob,'d');
                data.append('o','2');
                data.append('c',block_crc32);
                data.append('p',this.part);
                data.append('i',this.fileId);
                let response = fetch(this.scriptUrl,{
                    method: 'POST',
                    body: data
                })
                .then((response) => {
                    return response.json();
                })
                .then((result) => {
                    this.blockList[this.part]=block_crc32;
                    this.uploadBlock();
                });

            });
            this.part=0;
            this.blockCount=Math.floor(this.file.size/this.blockSize);
            this.uploadBlock();

        }).catch((error) => {
            this._error(error);
        });
    };

    uploadBlock(){ 
        this._progress();
        if(this.blockList[this.part]===undefined){

            let begin=this.blockSize*this.part;
            let end=begin+this.blockSize;
            let block=this.file.slice(begin,end);
            this.reader.readAsArrayBuffer(block);

        }else if(this.part<this.blockCount){
            this.part++;
            this.uploadBlock();
        }else{
            this.doneUpload();
        }

    }

    doneUpload(){
        let all_crc32='';
        for(let i=0;i<=this.blockCount;i++){
            if(this.blockList[i]===undefined){
                this._error(new Error(''));
                return;
            }else{
                all_crc32+=this.blockList[i];
            }
        }
        all_crc32='';
        let data=new FormData();
        data.append('o','3');
        data.append('i',this.fileId);
        data.append('c',all_crc32);
        let response = fetch(this.scriptUrl,{
            method: 'POST',
            body: data
        })
        .then((response) => {
            return response.json();
        })
        .then((result) => {
            if(result['s']==true){
                this._successful();
            }else{
                this._error(new Error(''));
            }
        });
    }

    retry(){
        this.upload();
    }

    _error(error){
        this.errorFunction(error);
    }

    _successful(){
        this.successfulFunction();
    }

    _progress(){
        let uploadedSize=this.part*this.blockSize;
        if(uploadedSize>this.file.size){
            uploadedSize=this.file.size;
        }
        let percentage=100*Object.keys(this.blockList).length/this.blockCount;
        if(percentage>=100){
            percentage=100;
        }
        let uploadedBlocks=Object.keys(this.blockList).length;
        if(uploadedBlocks>this.maxUploadedBlocks){
            this.maxUploadedBlocks=uploadedBlocks;
            this.progressFunction({
                blockSize: this.blockSize,
                totalBlocks: this.blockCount,
                uploadedBlocks: uploadedBlocks,
                totalSize: this.file.size,
                uploadedSize: uploadedSize,
                percentage: percentage
            });
        }
    }

    error(errorFunction){
        this.errorFunction=errorFunction;
    }

    successful(successfulFunction){
        this.successfulFunction=successfulFunction;
    }

    progress(progressFunction){
        this.progressFunction=progressFunction;
    }

    info(){
        let file_id=this.file.name+this.file.type+this.file.size.toString()+this.file.lastModified.toString();
        return {
            'id': file_id,
            'name': this.file.name,
            'size': this.file.size,
            'type': this.file.type
        }
    }
    
};
