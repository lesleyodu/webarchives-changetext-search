import java.util.*;
import java.io.*;

public class JSONSplitter {
    public static void main(String[] args) throws Exception {
        Scanner json = new Scanner(new File(args[0]));
        json.nextLine();
        String jsonStart = args[0].substring(0,args[0].length()-5);
        int ct = 0;
        System.out.println("Writing to: " + jsonStart + "-00000000.json");
        BufferedWriter writer = new BufferedWriter(new FileWriter(jsonStart + "-00000000.json", true));
        while (json.hasNextLine()) {
            String line = json.nextLine();
            if (line.equals("]")) break;

            if (line.matches("  },?")) { //remove two spaces for unicode cleaning
              ct++;
              if (ct % 1000 == 0) {
                  String filename = jsonStart + "-" + String.format("%08d", ct / 1000) + ".json";
                  System.out.println("Writing to: " + filename);
                  writer.append("  }\r\n");
                  writer.close();
                  writer = new BufferedWriter(new FileWriter(filename, true));
              }
              else {
                writer.append(line + "\r\n");
              }
            }
            else {
              writer.append(line + "\r\n");
            }
        }
        writer.close();
        System.out.println("Total documents: " + ct);
    }
}