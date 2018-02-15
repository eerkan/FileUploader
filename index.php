<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>File Uploader</title>
    <script defer src="fileuploader.js"></script>
    <script>
        document.onreadystatechange=function(){
            if(document.readyState==='complete'){
                var fu=new FileUploader(
                    'fu.php',
                    document.querySelector('form#file input[type="file"]')
                );
                fu.progress(function(e){
                    document.querySelector('progress#progress').value=parseInt(e.percentage);
                    console.log('%'+e.percentage+' uploaded');
                });
                fu.error(function(e){
                    console.log(e.message);
                    alert(e.message);
                });
                fu.successful(function(e){
                    console.log('Successful');
                    alert('Successful');
                });
                document.querySelector('form#file').onsubmit=function(){
                    console.log(fu.info());
                    fu.upload();
                    return false;
                };
            }
        };
    </script>
    <style>
        @-webkit-keyframes animate-stripes {
            100% { background-position: -100px 0px; }
        }

        @keyframes animate-stripes {
            100% { background-position: -100px 0px; }
        }

        progress {
            -webkit-appearance: none;
            appearance: none;
            border: none;
            height: 20px;
            width: 100%;
            margin-top: 10px;
        }

        progress::-webkit-progress-bar {
            background: #ddd;
            box-shadow: inset 0px 0px 10px 5px #bbb;
            border-radius: 20px;
        }

        progress::-webkit-progress-value {
        background-image:
            -webkit-linear-gradient(-45deg, 
                                    transparent 33%, rgba(0, 0, 0, .1) 33%, 
                                    rgba(0,0, 0, .1) 66%, transparent 66%),
            -webkit-linear-gradient(top, 
                                    rgba(255, 255, 255, .25), 
                                    rgba(0, 0, 0, .25)),
            -webkit-linear-gradient(left, #09c, #f44);

            border-radius: 20px; 
            background-size: 35px 20px, 100% 100%, 100% 100%;
            -webkit-animation: animate-stripes 0.1s linear infinite;
                animation: animate-stripes 0.1s linear infinite;
        }
    </style>
</head>
<body>
    <form id="file" method="post" action="#">
        <input type="file"/>
        <input type="submit" value="Upload File"/>
        <progress value="0" max="100" id="progress"></progress>
    </form>
</body>
</html>