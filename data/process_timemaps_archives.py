import pandas as pd
from datetime import datetime
from urllib.parse import urlparse

def get_archive(urim):
  #url = 'https://web.archive.org/web/20100226083132/http://climate.nasa.gov:80/news/index.cfm?NewsID=271'
  o = urlparse(urim)
  return o.hostname

date16 = datetime(2016, 7, 1,  0,  0,  2)
date16begin = datetime(2015,12,31,23,59,59)
date20 = datetime(2020, 7, 1, 0, 0, 2)
date20begin = datetime(2019,12,31,23,59,59)

with open('stat_all_merge.txt') as f:
   s = f.readlines()
   
ids = []

count = 0
lc = 0
for line in s:
    lc = lc + 1
    parts = line.split()
    if parts[1] == '200' and parts[2] == '200':
        count = count + 1
        ids.append(parts[0])

print('200/200: ', count)
print('total pairs: ', lc)

wa2016 = {}
wa2020 = {}

filtswitch = False
lastid = '00000'
for id in ids:
    print(id)
    loc = 'timemaps'
    
    if id < lastid:
        filtswitch = True
        
    if not filtswitch:
        dig = id[0]
        loc += dig
    else:
        loc = 'filtered-timemaps'
    jso = pd.read_json(loc + '/' + id + '.json')
    mementos = jso.loc['list']['mementos']
    thiswa2016 = {}
    thiswa2020 = {}
    for memento in mementos:
        mdate = memento['datetime'].split('-')
        mdt = datetime(int(mdate[0]), int(mdate[1]), int(mdate[2][0:2]),  0,  0,  1)
        wa = get_archive(memento['uri'])
        if mdt > date16begin and mdt < date16:
            thiswa2016.setdefault(wa, 0)
            thiswa2016[wa] = thiswa2016[wa] + 1
        if mdt > date20begin and mdt < date20:
            thiswa2020.setdefault(wa, 0)
            thiswa2020[wa] = thiswa2020[wa] + 1     
    for thiswa in thiswa2016:
        wa2016.setdefault(thiswa, 0)
        wa2016[thiswa] = wa2016[thiswa] + 1
    for thiswa in thiswa2020:
        wa2020.setdefault(thiswa, 0)
        wa2020[thiswa] = wa2020[thiswa] + 1 
    lastid = id
        
print(wa2016)
print(wa2020)