//https://github.com/ukwa/webarchive-discovery/blob/master/warc-indexer/src/main/java/uk/bl/wa/indexer/WARCIndexer.java
//https://stackoverflow.com/questions/11791803/update-a-new-field-to-existing-document
import org.apache.lucene.index.*;
import org.apache.lucene.store.*;
import java.nio.file.Paths;
import org.apache.lucene.document.*;
import java.util.*;
import org.apache.lucene.analysis.TokenStream;
import org.apache.lucene.analysis.standard.StandardAnalyzer;
import org.apache.lucene.analysis.Analyzer;
import org.apache.lucene.analysis.tokenattributes.CharTermAttribute;
import org.apache.lucene.search.highlight.*;
import java.net.URI;

//https://solr.apache.org/guide/6_6/uploading-data-with-index-handlers.html#UploadingDatawithIndexHandlers-JSONFormattedIndexUpdates
//https://stackoverflow.com/questions/11791803/update-a-new-field-to-existing-document

public class ValidityRangeIndexWriter {
   public static void main(String[] args) throws Exception {
   
      ExecutionTimer timer = new ExecutionTimer();
      timer.start();
      
      String portrem = "";
   
      DirectoryReader reader = DirectoryReader.open(FSDirectory.open(Paths.get("index")));
      //int nd = reader.numDocs();
      int nd = reader.maxDoc();
      //not checking for deleted documents
      //to do: just save the most recent version of that id
      HashMap<String, TreeSet<TemporalDocument>> map = new HashMap<String, TreeSet<TemporalDocument>>();
      for (int i = 0; i < nd; i++) {
         Document doc = reader.document(i);
         List<IndexableField> fields = doc.getFields();
         boolean hasContent = false;
         boolean hasTitle = false;
         for (IndexableField field : fields) {
            if (field.name().equals("content")) hasContent=true;
            if (field.name().equals("title")) hasTitle=true;
         }
         if (hasContent && hasTitle) {
            String u = doc.getField("url_norm").stringValue();
            String uu = doc.getField("id").stringValue();
            String port2 = "";
            //to do:
            //https://stackoverflow.com/questions/18574154/urisyntaxexception-illegal-character-in-query
            //Exception in thread "main" java.net.URISyntaxException: Illegal character in query at index 65: http://federalregister.gov/api/v1/documents.rss?conditions[term]="magnuson-stevens+act"+|+fisheries
            //try {
              String u_noq = "";
              String u2 = u;
              //rfc 2396 ? first allowed at query, allowed in anchor
              //https://github.com/internetarchive/surt/blob/master/surt/handyurl.py
              if (u.contains("?") && (u.indexOf("#") == -1 || u.indexOf("?") < u.indexOf("#"))) {
                  u_noq = u.substring(u.indexOf("?"));
                  u2 = u.substring(0, u.indexOf("?"));
              }
              URI portnorm = new URI(u2);
              if (portnorm.getPort() == 80 || portnorm.getPort() == 443) { 
                URI noport = new URI(portnorm.getScheme(), portnorm.getUserInfo(), portnorm.getHost(), -1,
                       portnorm.getPath(), portnorm.getQuery(), portnorm.getFragment());
                u2 = noport.toString();
                u2 += u_noq;
                u = u2;
                portrem += ",\n  {\n    \"id\": \"" + uu + "\",\n    \"url_norm\": {\"set\":\""+u+"\"}\n  }";
                port2 = u;
              }
            //}
            //catch (Exception e) { }
            
            //String u = uu.substring(uu.indexOf("/")+1);
            if (!map.containsKey(u)) map.put(u, new TreeSet<TemporalDocument>());
            
            HashSet<String> hashref = new HashSet<String>();
            HashMap<String, Integer> termcts = new HashMap<String, Integer>();
            Analyzer an = new StandardAnalyzer();
            TokenStream stream = TokenSources.getTokenStream("content", null, doc.getField("content").stringValue(), an, -1);
            CharTermAttribute cattr = stream.addAttribute(CharTermAttribute.class);
            stream.reset();
            while (stream.incrementToken()) {
               String term = cattr.toString();
               hashref.add(term);
               if (!termcts.containsKey(term)) termcts.put(term, 0);
               termcts.put(term, termcts.get(term) + 1);
            }
            stream.end();
            stream.close();
            
            TemporalDocument this_doc = new TemporalDocument(uu, i, doc.getField("wayback_date").numericValue().longValue(), hashref, termcts);
            this_doc.setPortChange(port2);
            TreeSet<TemporalDocument> mapgetu = map.get(u);
            boolean founddup = false;
            for (TemporalDocument that_doc : mapgetu) {
                if (that_doc.getWaybackDate().equals(this_doc.getWaybackDate())) {
                    //lucene deletions
                    that_doc.replaceWith(this_doc);
                    founddup = true;
                }
            }
            
            if (!founddup) map.get(u).add(this_doc);
            
         }
      }
      
      for (Map.Entry<String, TreeSet<TemporalDocument>> entry : map.entrySet()) {
          String key = entry.getKey();
          TreeSet<TemporalDocument> value = entry.getValue();
          TemporalDocument prev = null;
          for (TemporalDocument td : value) {
              if (prev != null) prev.setNext(td);
              prev = td;
          }
      }
      System.out.println("[");
      int json_doc_ct = 0;
      boolean fencepost = false;
      for (Map.Entry<String, TreeSet<TemporalDocument>> entry : map.entrySet()) {
          String key = entry.getKey();
          TreeSet<TemporalDocument> value = entry.getValue();
          int docVerOrdinal = (value.size() == 1) ? -1 : 1;
          for (TemporalDocument td : value) {
              if (fencepost) System.out.println(",");
              else fencepost = true;
              System.out.print(td.getSolrUpdateJSON(docVerOrdinal));
              docVerOrdinal++;
              json_doc_ct++;
          }
      }
      //System.out.println(portrem);
      System.out.println("\n]");
      
      timer.end();
      System.out.println("Execution Time: " + timer.duration() + " nanoseconds");
      System.out.println("# Validity Range Docs: " + json_doc_ct + " (/" + nd + ")");
    
   }
}