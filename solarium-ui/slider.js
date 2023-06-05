//https://stackoverflow.com/questions/67794511/change-div-based-on-slider-value

wb_arr = [];
diff_arr = [];
const NAV_BEGIN = 0;
const NAV_END = 3;
const NAV_COAL_REV = 1;
const NAV_COAL_FORW = 2;

function updateValue() {
  var rangeInput = document.getElementById("rangeInput").value;
  //var box = document.getElementById("box");
  //box.textContent = wb_arr[rangeInput-1] + " to " + wb_arr[rangeInput];
  var wb_date1 = document.getElementById("wb_date1");
  wb_date1.innerHTML = wb_arr[rangeInput-1];
  var wb_date2 = document.getElementById("wb_date2");
  wb_date2.innerHTML = wb_arr[rangeInput];
  var diff_td = document.getElementById("diff_td");
  diff_td.innerHTML = '<pre>' + diff_arr[rangeInput - 1].diff + '</pre>';
}

function wb_push($date) {
    wb_arr.push($date);
}

function diff_push($diff) {
    diff_arr.push($diff);
}

function navigateDiff(nav_type) {
    var rangeInputDom = document.getElementById("rangeInput");
    if (nav_type == NAV_BEGIN) {
        rangeInputDom.value = 1;
    }
    else if (nav_type == NAV_END) {
        rangeInputDom.value = wb_arr.length - 1;
    }
    else if (nav_type == NAV_COAL_FORW) {
        var idx = rangeInputDom.value;
        idx = idx + 1;
        while(idx < wb_arr.length - 1 && diff_arr[idx - 1].diff == 'Page versions are identical') {
            idx = idx + 1;
        }
        rangeInputDom.value = idx;
    }
    else if (nav_type == NAV_COAL_REV) {
        var idx = rangeInputDom.value;
        idx = idx - 1;
        while(idx > 0 && diff_arr[idx - 1].diff == 'Page versions are identical') {
            idx = idx - 1;
        }
        rangeInputDom.value = idx;
    }
    updateValue();
}
