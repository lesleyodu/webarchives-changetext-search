import java.time.format.DateTimeFormatter;
//import java.util.Date;
//import java.util.Calendar;
import java.time.ZoneId;
import java.time.Instant;
import java.util.*;//HashSet, HashMap, Entry

public class TemporalDocument implements Comparable<TemporalDocument> {
    private long wayback_date;
    private int docnum;
    private String id;
    private TemporalDocument next;
    private Date wbdate;
    private HashSet<String> terms;
    private HashMap<String, Integer> termCounts;
    private String aterms;
    private String portChange;
    
    public static final DateTimeFormatter WAYBACK_DATETIME =
            DateTimeFormatter.ofPattern("yyyyMMddHHmmss").withZone(ZoneId.of("UTC"));

    //Solr ID, Lucene ID, waybackdate as long (yyyyMMddHHmmss)
    public TemporalDocument(String i, int n, long w, HashSet<String> t, HashMap<String, Integer> tc) {
        id = i; //needed for JSON
        docnum = n; //used in compareTo
        wayback_date = w;
        wbdate = new Date(Instant.from(WAYBACK_DATETIME.parse(""+wayback_date)).toEpochMilli());
        terms = t;
        termCounts = tc;
        portChange = "";
    }
    
    public void replaceWith(TemporalDocument that) {
        id = that.id;
        docnum = that.docnum;
        wayback_date = that.wayback_date;
        wbdate = that.wbdate;
        terms = that.terms;
        termCounts = that.termCounts;
        portChange = that.portChange;
    }
    
    //used with TreeSet
    public int compareTo(TemporalDocument that) {
        if (this.wayback_date > that.wayback_date) return 1;
        else if (this.wayback_date < that.wayback_date) return -1;
        else return this.docnum - that.docnum;
    }
    
    public String toString() {
        //Solr ID has date and URL hash
        return "TemporalDocument with solr id " + id;
    }    
    public String getSolrUpdateJSON(int docVerOrdinal) {
    
        String calcdterms = getDeletedTerms();
        String dterms = (calcdterms.isEmpty()) ? "" : ",\n    \"deleted_term\": {\"set\":\"" + calcdterms + "\"}";
        
        String ordstr = "";
        String calcsdterms = "";
        String sdterms = "";
        String atermstr = "";
        String portUpdate = "";
        if (docVerOrdinal != -1) {
        
          ordstr =  "\n    \"doc_ver_ordinal\": {\"set\":\"" + docVerOrdinal + "\"}" ;
          calcsdterms = getSemiDeletedTerms();
          sdterms = (calcsdterms.isEmpty()) ? "" : ",\n    \"semi_del_term\": {\"set\":\"" + calcsdterms + "\"}";
          portUpdate = (portChange.isEmpty()) ? "" : ",\n    \"url_norm\": {\"set\":\"" + portChange + "\"}";
          
          //think about whether only deleted terms should be indexed for additions
          String atermjoin = aterms;
          if (atermjoin == null) {
            atermjoin = "";
            for (String term : terms) {
              atermjoin += term + " ";
            }
          }
          atermstr = (atermjoin.isEmpty()) ? "" : ",\n    \"added_term\": {\"set\":\"" + atermjoin + "\"}";
        }
    
        return "  {\n    \"id\": \"" + id + "\"," +
                  "\n    \"validity_range\": {\"set\":\"[" + getDateRange() + "]\"}" +
                  dterms +
                  sdterms +
                  portUpdate + 
                  atermstr +
                  //ordstr +
                  "\n  }";
    }
    
    public void setPortChange(String s) {
        portChange = s;
    }
    
    //needed because Sets don't have indices
    public void setNext(TemporalDocument n) {
        next = n;
    }
    public TemporalDocument getNext() { return next; }

    //static for last element in TreeSet next calc
    public static String getSolrDateString(Date date) {
       return DateTimeFormatter.ISO_INSTANT.format(date.toInstant());
    }
    
    //Date math/format functions:
    
    public Date getWaybackDate() {
        return wbdate;
    }
    
    public String getWaybackDateStr() {
        return getSolrDateString(getWaybackDate());
    }
    
    public String getDateRange() {
        //https://solr.apache.org/guide/6_6/working-with-dates.html
        //https://www.acorel.nl/2021/05/working-with-date-ranges-in-solr/
    
        String nextDateStr;
        if (next == null) {
            Calendar c = Calendar.getInstance();
            c.setTime(this.getWaybackDate());
            c.add(Calendar.DATE, 30);
            nextDateStr = getSolrDateString( c.getTime() ); 
        }
        else {
            //inclusive not exclusive. subtract 1 second for exclusive
            nextDateStr = next.getWaybackDateStr();
        }

        return this.getWaybackDateStr() + " TO " + nextDateStr;
    }
    
    public String getDeletedTerms() {
       if (next == null) return "";

       HashSet<String> value = next.terms;
       HashSet<String> valueP = this.terms;
          
       HashSet<String> deletedValues = new HashSet<String>(valueP);
       deletedValues.removeAll(value);
       
       HashSet<String> atermset = new HashSet<String>(value);
       atermset.removeAll(valueP);
       next.aterms = "";
       for (String term : atermset) {
          next.aterms += term + " ";
       }
       
       String ret = "";
       for (String term : deletedValues) {
          ret += term + " ";
       }
       //return deletedValues.toString().replace(",","");
       return ret;
    }
    
    public String getSemiDeletedTerms() {
       if (next == null) return "";

       HashMap<String, Integer> map = next.termCounts;
       HashMap<String, Integer> mapP = this.termCounts;
          
       HashSet<String> deletedValues = new HashSet<String>();
       for (Map.Entry<String, Integer> entry : mapP.entrySet()) {
          String key = entry.getKey();
          int value = entry.getValue();
          int valueN = (map.containsKey(key)) ? map.get(key) : 0;
          if (value > valueN && valueN > 0) {
            deletedValues.add(key);
          }
        }
       
       String ret = "";
       for (String term : deletedValues) {
          ret += term + " ";
       }
       //return deletedValues.toString().replace(",","");
       return ret;
    }
}