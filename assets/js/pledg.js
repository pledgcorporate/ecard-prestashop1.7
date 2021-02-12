(function ($) {
  const regex = /payment-option-([0-9]{1,2})-container/;
  $(document).ready(function () {
    $('#pledgButton').click();
    document.querySelectorAll('input[name="payment-option"]').forEach((elem) => {
      elem.addEventListener("change", function(event) {
        var box = event.target.parentNode.parentNode.id.match(regex)[1];
        box = document.querySelector('#payment-option-'+box+'-additional-information').querySelector('.payment-box');
        if(box !== null){
          try{
            let locale = box.querySelector('input[type="hidden"].locale').value;
            let payment_detail_trad = JSON.parse(box.querySelector('input[type="hidden"].payment_detail_trad').value);
            let urlAPI = JSON.parse(box.querySelector('input[type="hidden"].url_api').value);
  
            var child = box.querySelector('#payment-detail-container');
            if(child !== null){
              child.remove();
            }
            child = document.createElement('div');
            child.classList.add('spinner-parent');
            child.id = "payment-detail-container";
            child.innerHTML = '<span class="spinner-border"></span>';
  
            box.appendChild(child);
  
            var xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function() {
              if (this.readyState == 4 && this.status == 200) {
                var data = JSON.parse(this.responseText);
                if(typeof(data['INSTALLMENT']) !== 'undefined'){
                  APIResp = data['INSTALLMENT'];
                  let ret = "<div class='screen-section' style='padding-top: 0px;'>";
                  let feesCalc = (APIResp[0].fees/100).toFixed(2) ;
                  for (let i = 0; i < APIResp.length; i++) {
                    // New line
                    let amountCalc = (APIResp[i].amount_cents/100).toFixed(2) ;
                    if(i===0){
                      amountCalc =((APIResp[i].amount_cents+APIResp[0].fees)/100).toFixed(2);
                      if(payment_detail_trad.currencySign === 'before'){
                        feesCalc = payment_detail_trad.currency + feesCalc;
                      }
                      else{
                        feesCalc = feesCalc + payment_detail_trad.currency;
                      }
                    }
                    if(payment_detail_trad.currencySign === 'before'){
                      amountCalc = payment_detail_trad.currency + amountCalc;
                    }
                    else{
                      amountCalc = amountCalc + payment_detail_trad.currency;
                    }
                    ret +=`<p style="margin-top: 30px;">
                      <b style="float: left;">`+
                      payment_detail_trad.deadline + ' ' + (i+1) + ' ' +
                      payment_detail_trad.the + ' ' +
                      new Date(APIResp[i].payment_date).toLocaleDateString(locale) +
                      `</b><b style="float: right; text-align: right;">`
                      + amountCalc + '</b>';
                    if(i===0){
                      ret +=`<br>
                      <b style="float: right;"><span style="font-size: 0.85em;"> ` +
                      payment_detail_trad.fees.replace('%s', feesCalc) +
                      `</span></b></p>`;
                    } 
                    ret += '</p><div style="clear: both; margin-bottom: 30px;"></div>';
                  }
                  ret += '</div>';
                  child.innerHTML = ret;
                  child.classList.remove('spinner-parent');
                }
                else if(typeof(data['DEFERRED']) !== 'undefined'){
                  APIResp = data['DEFERRED'];
                  let amountCalc = (APIResp.amount_cents/100).toFixed(2);
                  if(payment_detail_trad.currencySign === 'before'){
                    amountCalc = payment_detail_trad.currency + amountCalc;
                  }
                  else{
                    amountCalc = amountCalc + payment_detail_trad.currency;
                  }
                  let ret = "<div class='screen-section' style='padding-top: 0px;'>";
                  ret += "<p><b>";
                  ret += payment_detail_trad.deferred.replace('%s1', amountCalc).replace('%s2', new Date(APIResp.payment_date).toLocaleDateString(locale));
                  ret += "</b></p></div>";
                  child.innerHTML = ret;
                  child.classList.remove('spinner-parent');
                }
                else{
                  child.innerHTML = "";
                  child.classList.remove('spinner-parent');
                }
              }
            };
            xhttp.open("POST", urlAPI['url'], true);
            xhttp.send(JSON.stringify(urlAPI['payload']));
          }
          catch(e){
            console.log(e)
          }
        }
      });
    });
  });
})(jQuery);