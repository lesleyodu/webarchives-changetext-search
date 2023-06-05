import json
import heapq

#vout112722.txt
#https://countwordsfree.com/stopwords
with open('vout012223.txt') as f:
    s = f.read()
s = s[1:s.rindex(']')-1]

add = json.loads('{"stuff": [' + s + ']}')


terms = {}
for a in add['stuff']:
    if 'deleted_term' in a:# or 'semi_del_term' in a:
        terms1 = a['deleted_term']['set'].split()
        for t in terms1:
            terms.setdefault(t, 0)
            terms[t] = terms[t] + 1
h = []
for t in terms:
    heapq.heappush(h, (-terms[t], t))
for i in range(1, 100):
    print(heapq.heappop(h))
    