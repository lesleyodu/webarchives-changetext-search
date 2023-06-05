import org.apache.solr.client.solrj.*;
import org.apache.solr.client.solrj.impl.HttpSolrClient;
import java.util.*;
import org.apache.solr.common.*;
import org.apache.solr.common.params.*;
import org.apache.solr.client.solrj.response.*;
//https://solr.apache.org/guide/8_1/using-solrj.html
import org.archive.wayback.util.url.AggressiveUrlCanonicalizer;
import org.archive.wayback.util.url.*;
import org.archive.wayback.util.*;
import org.archive.wayback.surt.*;
import org.archive.url.SURT;
import org.archive.url.*;
//http://crawler.archive.org/apidocs/org/archive/util/SURT.html
import org.apache.commons.codec.binary.Base32;
import java.security.MessageDigest;
import java.nio.charset.StandardCharsets;

public class GenerateCDX {

  public static final boolean INCLUDE_SURT_SCHEME = false;
  //https://www.javatips.net/api/archive-commons-master/archive-commons/src/main/java/org/archive/url/HandyURL.java


   public static SolrClient getSolrClient() {
      final String solrUrl = "http://localhost:8983/solr";
      return new HttpSolrClient.Builder(solrUrl)
         .withConnectionTimeout(10000)
         .withSocketTimeout(60000)
         .build();
   }

   public static void main(String[] args) throws Exception {
      SolrClient client = getSolrClient();
      //missing: digest
      //wrong: length (10502 vs 11418 (916),13984 vs 12321  (1663), so no pattern)
      //surt isn't working - uuri needed
      String[] fields = {"wayback_date", "host_surt", "url",  "content_type_served", "status_code", "hash", "content_length", "source_file_offset", "source_file"};
      String[] cdxFields = {"wayback_date", "host_surt",  "url", "mime", "status", "digest", "length", "offset", "filename"};
      String fieldStr = "";
      for (String field : fields) fieldStr += field + ",";
      fieldStr = fieldStr.substring(0, fieldStr.length() - 1);
      
      String qq = (args.length > 0) ? args[0] : "20210212232029/FvIROUMdzsFzDPZYu/Zizg==";
      //20210212232031/N8SbXF5TqeNVD2cePg39Ag==
      //20220304010520/zlO8V4RlPGs1XjJKm0pXXg== 238378 239170
      
      Map<String, String> queryParamMap = new HashMap<String, String>();
      queryParamMap.put("q", "id:" + qq);
      queryParamMap.put("fl", fieldStr);
      MapSolrParams queryParams = new MapSolrParams(queryParamMap);
   
      QueryResponse response = client.query("netarchivebuilder", queryParams);
      SolrDocumentList documents = response.getResults();
   
      System.out.println("Found " + documents.getNumFound() + " documents");
      for(SolrDocument document : documents) {
      
         /*KeyMakerUrlCanonicalizer km = new KeyMakerUrlCanonicalizer();
         String url = (String) document.getFirstValue("url");
         String surt = km.urlStringToKey(url);
         System.out.print(surt + " ");*/
         String url = (String) document.getFirstValue("url");
         //String surt = SURTTokenizer.prefixKey(url);

         
         /*Collection<Object> surtsolr =  document.getFieldValues("host_surt");
         ArrayList<Object> surtsolrarr = new ArrayList<Object>(surtsolr);
         String maxsurt = "";
         for (Object surtobj : surtsolrarr) {
             String surtobjstr = (String) surtobj;
             if (surtobjstr.length() > maxsurt.length()) maxsurt = surtobjstr;
         }
         System.out.print(maxsurt.substring(1, maxsurt.length()-1));*/
         
         //look into this - has imports
         //line 616
         //https://github.com/ukwa/webarchive-discovery/blob/master/warc-indexer/src/main/java/uk/bl/wa/indexer/WARCIndexer.java
         
         //UURI uurihelper = UURIFactory.getInstance(url);
         //String surthelper = uurihelper.getSurtForm();
         //System.out.println(surthelper);
         
         //String surt = SURT.fromURI(url);//.replaceFirst("^https://", "http://"));
         //if (surt.endsWith("/")) surt = surt.substring(0, surt.length()-1);
         //System.out.print(surt.substring(surt.indexOf(")"))+ " ");
         
         //String surt = SURT.toSURT(url);
         //System.out.print(surt + " ");
         
         HandyURL hURL = URLParser.parse(url);
         String surt = hURL.getSURTString(INCLUDE_SURT_SCHEME);
         //String surt = SURT.toSURT(url_fixup);
         System.out.print(surt + " ");
         
      
         Long wbd = (Long) document.getFirstValue("wayback_date");
         System.out.println(wbd + " {");
         
         for (int i = 2; i < fields.length; i++) {
           String field = fields[i];
           Object f = document.getFirstValue(field);
           String fs = f.toString();
           if (cdxFields[i].equals("mime")) {
              if (fs.indexOf("; ") > -1) {
                fs = fs.substring(0, fs.indexOf("; "));
              }
            }
            if (cdxFields[i].equals("digest")) {
              /*MessageDigest sha1 = MessageDigest.getInstance( "SHA-1" );
              byte[] digest = sha1.digest(fs.getBytes(StandardCharsets.UTF_8));
              Base32 b32 = new Base32();
              fs = b32.encodeAsString(digest);*/
              fs = fs.substring(5);
              //fs = "tbd";
            }
           System.out.println("  \"" + cdxFields[i] + "\": \"" + fs + "\"");
        /* String id = (String) document.getFirstValue("id");
         String url = (String) document.getFirstValue("url");
      
         System.out.println("id: " + id );
         System.out.println("url: " + url );*/
         }
         System.out.println("}");
      }
   }
}