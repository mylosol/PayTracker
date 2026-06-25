// JavaScript Document
            function showHideMore(obj) {
                var contObj = obj.parentNode.getElementsByTagName('div')[0];
                var status = (contObj.style.display == 'block')? 'none' : 'block'
                contObj.style.display = status;
            }
            window.onload=function(){
                oMoreLessSpans = document.getElementById('faq').getElementsByTagName('span');
                for(i=0; i < oMoreLessSpans.length; i++){
                    oMoreLessSpans[i].onclick=function(){showHideMore(this);}
                }
            }