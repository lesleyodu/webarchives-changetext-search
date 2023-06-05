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

public class WindowsUnicodeCleaner {
   public static void main(String[] args) throws Exception {
   
      //String the_id = "20211209121030/cPzCnXwu5abEref2x7MfQw==";
      DirectoryReader reader = DirectoryReader.open(FSDirectory.open(Paths.get("index")));
      int nd = reader.maxDoc();
      HashMap<String, String> map = new HashMap<String, String>();
      
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
         
            String uu = doc.getField("id").stringValue();
         
         //if (uu.equals(the_id)) {
            String cont = doc.getField("content").stringValue();
            //System.out.println(cont);
             //cont = cont.replaceAll("\u00e2\u20ac\u201c", "--");
             String mapcl = cleanse(cont);
             if (!cont.equals(mapcl)) {
             
                  map.put(uu, mapcl);
             }
             else {
                 if (map.containsKey(uu)) {
                     map.remove(uu);
                 }
             }
         }
      }
      
      //System.out.println(map.get(the_id));
      System.out.println("[");
      for (String doc_id : map.keySet()) {
     
      
         String escaped = map.get(doc_id).replace("\"", "\\\"");
         //escaped = escaped.replace(":", "\\\\:");
         
         //if (hasUnicode(escaped)) {
         
         System.out.println("{\"id\": \"" + doc_id + "\",");
         System.out.println(" \"content\": {\"set\":\"" + escaped + "\"},");
         System.out.println(" \"text\": {\"set\":\"" + escaped + "\"}\n},");
         //fencepost
         
         //}
      }
      System.out.println("]");
      
      
      
   }
   
   public static boolean hasUnicode(String str) {
       char[] arr = str.toCharArray();
       int count = 0;
       String output = "";
       for (int i = 0; i < arr.length; i++) {
           char c = arr[i];
           if (Character.UnicodeBlock.of(c) != Character.UnicodeBlock.BASIC_LATIN) {
              output += (c + ", " + Integer.toHexString(c)) + "\n";
              i++;
              count = 1;
              while (i < arr.length && Character.UnicodeBlock.of(arr[i]) != Character.UnicodeBlock.BASIC_LATIN) {
                 c = arr[i];
                 output += (c + ", " + Integer.toHexString(c))  + "\n";
                 i++;
                 count++;
              }
              if (count > 1) {
                  System.out.println(output);
                  return true;
              }
              else {
                  output = "";
              }
           }
       }
       return false;
   }
   
   public static String cleanse(String line) {
   //https://python.hotexamples.com/examples/unicodedata/-/digit/python-digit-function-examples.html
   // Ridiculous double conversions, UTF8 -> Windows 1252 -> UTF8:
      line = line.replaceAll("\u00ef\u00bf\u00bd", "--"); //long dash
          //https://stackoverflow.com/questions/1488866/how-to-replace-%C3%AF-%C2%BD-in-a-string
      line = line.replaceAll("\u00e2\u20acoe", "\"")        ;// opening double quote
      line = line.replaceAll("\u00e2\u20ac\u009c", "\"")    ;// opening double quote
      line = line.replaceAll("\u00e2\u20ac\u009d", "\"")    ;// closing double quote
      line = line.replaceAll("\u00e2\u20ac\u2122", "'")   ;// apostrophe
      line = line.replaceAll("\u00e2\u20ac\u201c", " -- ") ;// en dash?
      line = line.replaceAll("\u00e2\u20ac\u201d", " -- ") ;// em dash?
      
      line = line.replaceAll("\u00e2\u20ac\u0153", "\"")    ;// opening double quote
      line = line.replaceAll("\u00e2\u20ac\ufffd", "\"")    ;// closing double quote
   
      line = line.replaceAll("\u00e2\u0080\u0098", "'") ;// single quote?
      line = line.replaceAll("\u00e2\u0080\u0099", "'") ;// single quote?
      line = line.replaceAll("\u00e2\u0080\u009c", "\"")  ;// double quote?
      line = line.replaceAll("\u00e2\u0080\u009d", "\"")  ;// double quote?
      line = line.replaceAll("\u00c3\u009f", "\u00df")    ;// esset
      line = line.replaceAll("\u00c3\u0178", "\u00df")    ;// esset
      line = line.replaceAll("\u00c3\u00a4", "\u00e4")    ;// a umlaut
      line = line.replaceAll("\u00c3\u00b6", "\u00f6")    ;// o umlaut
      line = line.replaceAll("\u00c3\u00bc", "\u00fc")    ;// u umlaut
      line = line.replaceAll("\u00c3\u0084", "\u00c4")    ;// A umlaut: create no C4s after this
      line = line.replaceAll("\u00c3\u201e", "\u00c4")    ;// A umlaut: create no C4s after this
      line = line.replaceAll("\u00c3\u0096", "\u00d6")    ;// O umlaut
      line = line.replaceAll("\u00c3\u2013", "\u00d6")    ;// O umlaut
      line = line.replaceAll("\u00c3\u00bc", "\u00dc")    ;// U umlaut
      line = line.replaceAll("\u0080", "\u20ac")           ;// euro sign
      line = line.replaceAll("\u0095", "\u2022")           ;// bullet
      line = line.replaceAll("\u0099", "\u2122")           ;// trademark sign
    
      line = line.replaceAll("\u00c2\u00a0", " "); //errant nbsp
      line = line.replaceAll("\u00c2\u00bb", " >> "); //>>
      line = line.replaceAll("\u00e2\u0080\u00a2", "\u2022"); //bullet
      line = line.replaceAll("\u00c2\u00b7", "\u00b7"); //bullet
      //https://www.i18nqa.com/debug/utf8-debug.html
      line = line.replaceAll("\u00e2\u0080\u00ba", " > "); //arrow
      line = line.replaceAll("\u00e2\u20ac\u00ba", " > "); //arrow
      line = line.replaceAll("\u00c2\u00ba", " > "); //arrow
      line = line.replaceAll("\u00c2\u00a9", " (c) "); //copyright
      line = line.replaceAll("\u00c2\u00ae", " (r) "); //registered
      line = line.replaceAll("\u00c3\u00ad", "\u003d"); //i
      line = line.replaceAll("\u00e2\u0090\u00a6", "..."); //ellipsis
      line = line.replaceAll("\u00e2\u20ac\u00a6", "..."); //ellipsis
      line = line.replaceAll("\u00e2\u20ac\u02dc", "'"); //'
      line = line.replaceAll("\u00e2\u201e\u00a2", " (tm) "); //trademark
      line = line.replaceAll("\u00e2\u0084\u00a2", " (tm) "); //trademark
      
      line = line.replaceAll("\u00e2\u00e2\u201a\u00ac\u00e2\u201e\u00a2", "'"); //really bad
      line = line.replaceAll("\u00e2\u20ac\u2039", ""); //idk
      line = line.replaceAll("\u00e2\u20ac\u00af", ""); //idk
      line = line.replaceAll("\u00e2\u2020\u2019", ""); //idk  

      char c = '\u00a0';
      char r = '\u00e0';
      for (int i = 0; i <= 16; i++) {
          line = line.replaceAll("\u00c3" + c,"" + r);
          c++;
          r++;
      }
      c = '\u00b0'; 
      r = '\u00e0';
      for (int i = 0; i <= 16; i++) {
          line = line.replaceAll("\u00c3" + c,"" + r);
          c++;
          r++;
      }
      c = '\u00a0'; 
      r = '\u00a0';
      for (int i = 0; i <= 16; i++) {
          line = line.replaceAll("\u00c2" + c,"" + r);
          c++;
          r++;
      }
      c = '\u00b0'; 
      r = '\u00b0';
      for (int i = 0; i <= 16; i++) {
          line = line.replaceAll("\u00c2" + c,"" + r);
          c++;
          r++;
      }
      
      //https://gist.github.com/xijo/d4bad3953f7b9979dd91
      //https://github.com/rspeer/python-ftfy
       
      return line;
   }
}