import web_monitoring_diff
import os
from urllib.parse import parse_qs
import requests
import sys
import re
from bs4 import BeautifulSoup
import codecs
import uniseg.wordbreak
import string
from urllib.parse import urljoin

#https://learning.oreilly.com/library/view/python-cookbook/0596001673/ch03s19.html#pythoncook-CHP-3-SECT-19.4
sys.stdout = codecs.lookup('utf-8')[-1](sys.stdout)

sys.stdout.buffer.write(str.encode("Content-type: text/html\n\n"))

def get_rewrite_collection():
    return 'netarchivesuite'

def get_rewrite_address():
    return 'http://localhost:8060/' + get_rewrite_collection() + '/'

#http://localhost:8060/netarchivesuite/20220624035335/https://peterjson.medium.com/miracle-one-vulnerability-to-rule-them-all-c3aed9edeea2
def get_local_wb_uri(wburi, wbdate):
    return get_rewrite_address() + wbdate + '/' + wburi

query_parsed = parse_qs(os.environ['QUERY_STRING'])

uri1 = get_local_wb_uri(query_parsed['page'][0], query_parsed['wbdate1'][0])
uri2 = get_local_wb_uri(query_parsed['page'][0], query_parsed['wbdate2'][0])
page1 = requests.get(uri1)
page2 = requests.get(uri2)
comparison = web_monitoring_diff.html_diff_render(page1.text, page2.text)

anim_del_js_head = """
<head>
<style type="text/css">
<!--
del {text-decoration: none;}
ins {display: none;}
-->
</style>
<script type="text/javascript">
window.letterprint = new Array();
function sleep(ms) {
  //https://www.sitepoint.com/delay-sleep-pause-wait/
  return new Promise(resolve => setTimeout(resolve, ms));
}
function sleepFor(sleepDuration){
    var now = new Date().getTime();
    while(new Date().getTime() < now + sleepDuration){ 
        /* Do nothing */ 
    }
}
function printLetterByLetter(index, speed){ 

    if (letterprint.includes(index)) {
        return;
    }
    letterprint.push(index);

    var i = 0;
    var destination = 'wm-diff-del-wrapper' + index;
    var origin = 'wm-diff-ins-wrapper' + index;
    var destText = document.getElementById(destination).innerText;
    var originElem = document.getElementById(origin);
    var originText = null;
    if (originElem !== null) {
        originText = originElem.innerText;
        document.getElementById(origin).innerText = "";
    }
    var anchor = '<a class="wm-diff-anchor" id="wm-diff-del' + index +'"> </a>';
    var j = 0;
    var wc = 0;
    var paus = 0;
    window.location = window.location.origin + window.location.pathname + window.location.search + '#wm-diff-del' + index
    var interval = setInterval(function(){
        if (j <= destText.length) {
           if (destText.substring(destText.length - j - 1, destText.length - j) == ' ') {
               wc++;
           }
           document.getElementById(destination).innerHTML = anchor + destText.substring(0, destText.length - j);
           if (wc > 3) {
               var jadd = destText.substring(0, destText.length - j).lastIndexOf(' ');
               if (jadd >= 0) {
                   j += (destText.substring(0, destText.length - j).length - jadd);
                   //alert(document.getElementById(destination).innerHTML);
                   if (wc > 5) {
                       sleepFor(400);
                   }
                   wc++;
               }
               else {
                   if (paus < 2) {
                       paus++;
                       if (paus >= 1) {
                           sleepFor(400);
                       }
                   }
                   j++;
               }
           }
           else {
               j++;
           }
        }
        else {
           if (originElem !== null) {
               document.getElementById(origin).innerHTML += originText.charAt(i);
               i++;
           }
           if (originElem === null || i > originText.length){
               clearInterval(interval);
               index++;
               var nextdestination = 'wm-diff-del-wrapper' + index;
               var destelem = document.getElementById(nextdestination);
               if (destelem !== null) {
                   //printLetterByLetter(index, 100);
                   sleep(800).then(() => { printLetterByLetter(index, 100); });
               }
           }
        }
    }, speed);
}
</script>
<script type="text/javascript">
if(document.readyState === "complete" || document.readyState === "interactive") {
   printLetterByLetter(1, 100);
}
else {
    window.addEventListener("DOMContentLoaded", () => {
        // DOM ready! Images, frames, and other subresources are still downloading.
        printLetterByLetter(1, 100);
     });
      window.addEventListener("load", () => {
        // Fully loaded!
        
    });
}

</script>
"""
comparison['combined'] = comparison['combined'].replace('<head>', anim_del_js_head)

#Remove deletions that don't match the search term
comparison['combined'] = comparison['combined'].replace('<del class="wm-diff">', '<del class="wm-diff"><a class="wm-diff-anchor">_</a>')
comparison_soup = BeautifulSoup(comparison['combined'], 'html.parser')
dterm = query_parsed['dterm'][0]
dterm_regex = r"\b" + re.escape(dterm.lower()) + r"\b"
i = 1
for deletion in comparison_soup.find_all('del'):
    #to do: check to make sure right class - class="wm-diff"
    delcontent = deletion.renderContents().decode('utf-8').lower()
    delcontent = re.sub('<[^<]+>', '', delcontent)
    if ' ' not in dterm:
        dterm_match = re.search(dterm_regex, delcontent)
        if dterm_match is None:
        #if dterm not in delcontent:
            deletion.decompose()
        else:
            deletion['id'] = 'wm-diff-del-wrapper' + str(i)
            deletion.a['id'] = 'wm-diff-del' + str(i)
            deletion.a.string = ' '
            i = i + 1
    else:
        #phrase search
        delcontent_words = uniseg.wordbreak.words(delcontent)
        text_joined_with_spaces = " "
        for word in delcontent_words:
            if word in string.punctuation:
                continue
            if not word.strip():
                continue
            text_joined_with_spaces += word + " "
        if " " + dterm + " " not in text_joined_with_spaces:
            deletion.decompose()
        else:
            deletion['id'] = 'wm-diff-del-wrapper' + str(i)
            deletion.a['id'] = 'wm-diff-del' + str(i)
            deletion.a.string = ' '
            i = i + 1
comparison['combined'] = str(comparison_soup)
#Remove additions that aren't related to the search term
comparison['combined'] = re.sub('<del class="wm-diff" id="wm-diff-del-wrapper([0-9]+)(.+?)</del><ins(.+?)</ins>', '<del class="wm-diff" id="wm-diff-del-wrapper\g<1>\g<2></del><INS id="wm-diff-ins-wrapper\g<1>"\g<3></INS>', comparison['combined'])
comparison['combined'] = comparison['combined'].replace('<ins class="wm-diff">', '')
comparison['combined'] = comparison['combined'].replace('</ins>', '')
diffids = re.findall(r'wm-diff[\-a-z]+[0-9]+', comparison['combined'])
#sys.stdout.buffer.write(str.encode('<!--'+str(diffids)+' -->'))
lastid = 0
for i in range(len(diffids)):
    id = int(re.sub('[^0-9]', '', diffids[i]))
    notid = diffids[i].replace(str(id), '')
    if id < lastid:
       comparison['combined'] = comparison['combined'].replace(diffids[i], notid + str(lastid))
       #sys.stdout.buffer.write(str.encode('<!--'+diffids[i]+' -->'))
    elif id > lastid:
       lastid = id

#Switch the order of anchor link when it's nested
r1 = re.split(r"</a>",comparison['combined'])
r2 = []
for ir in range(len(r1)):
   rr = r1[ir]
   if '<a' not in rr and '</del>' in rr and ir > 0:
       r2.append(r1[ir - 1] + "</a>" +  rr + "</a>")
r3 = []
for rr in r2:
   r3.append(re.sub('<a([^>]+)>(.+)<a([^>]+)>(.+)</a>(.+)</a>', '<a\\3>\\4</a><a\\1>\\2\\5</a>', rr))
#comparison['combined'] = "<!-- " + str(r2) + "\n" + str(r3) + "-->" + comparison['combined']
for ir in range(len(r2)):
   comparison['combined'] = comparison['combined'].replace(r2[ir], r3[ir])

#relative links starting with slash dirty fix
comparison['combined'] = comparison['combined'].replace('"/' + get_rewrite_collection() + '/', '"' + get_rewrite_address())

#relative links not starting with slash long fix
soup = BeautifulSoup(comparison['combined'], 'html5lib')
scheme_pattern = re.compile('^[A-Za-z]+:\/\/.*');

page2nolb = page2.text.replace("\n", "")
page2urlorig = re.sub(".*wbinfo.url = \"([^\"]+)\";.*$", "\g<1>", page2nolb)

for img in soup.find_all('img'):
    if 'src' in img.attrs and img['src'] != '':
        m = scheme_pattern.match(img['src'])
        if (m == None):
            origsrc = urljoin(page2urlorig, img['src'])
            img['src'] = get_local_wb_uri(origsrc, query_parsed['wbdate2'][0])

#https://www.geeksforgeeks.org/how-to-remove-empty-tags-using-beautifulsoup-in-python/          
for x in soup.find_all('li'):
    # fetching text from tag and remove whitespaces
    if len(x.get_text(strip=True)) == 0:
        # Remove empty tag
        x.extract()

soup_html = str(soup)
#sys.stdout.buffer.write(str.encode('<!--' +  repr(str.encode(soup_html)) + '-->'))
#soup_html = re.sub("\\\\x[0-9a-f][0-9a-f]", '', repr(str.encode(soup_html)))
#Fix this better so it doesn't mutilate foreign languages 4/15/23
soup_html = re.sub(r'[^\x00-\x7f]',r'',soup_html)
#https://gist.github.com/tushortz/9fbde5d023c0a0204333267840b592f9
#soup_html = soup_html.replace('\xe2\x80\x99', "'")
#while "<ul></ul>" in soup_html or "<li></li>" in soup_html:
#  soup_html = soup_html.replace("<ul></ul>", "")
#  soup_html = soup_html.replace("<li></li>", "")
sys.stdout.buffer.write(str.encode(soup_html))