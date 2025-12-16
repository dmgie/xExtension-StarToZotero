
Send a FreshRSS entry to Zotero when you “star” (favorite) it.

*What it does*
- Hooks into FreshRSS’s =entries_favorite= event.  
- When an entry is starred, the extension posts the article (or a minimal fallback) to a Zotero user library.  
- Optional Zotero Translation Server support for richer metadata.  
- Allows extra tags, a target collection, and a configurable keyboard shortcut.

*Installation / Setup*
1. *Copy the extension* into FreshRSS’s extensions folder:  

   #+begin_src bash
   cp -r StarToZotero /path/to/FreshRSS/extensions/
   #+end_src

2. *Enable the extension* in FreshRSS → *Extensions* → *StarToZotero*.  
3. *Configure Zotero credentials* via the extension’s /Configure/ page:  

   | Setting | Description |
   |---|---|
   | *Zotero API key* | Private API key (found in Zotero → Settings → Keys). |
   | *Zotero user ID* | Numeric user ID (e.g., =123456=). |
   | *Collection key* | Key of the collection where items will be added. |
   | *Translation server URL* (optional) | URL of a Zotero Translation Server (e.g., =http://127.0.0.1:1969=). |
   | *Extra tags* | Comma-separated tags added to every item (default: =source:rss,inbox=). |
   | *Keyboard shortcut* | Single-character shortcut to trigger the star-to-Zotero action. |

4. Save the form – the extension is ready.

*Basic Usage*
1. *Star an article* in FreshRSS (click the ★ icon).  
2. The extension automatically sends the entry to Zotero:  
   - With a translation server → richer metadata.  
   - Without → a minimal =webpage= item (title + URL).  
3. Success or error notifications appear in FreshRSS (see =configure.phtml= for messages).  

*Keyboard shortcut* (if set): press the configured key while an entry is focused to star it and trigger the Zotero export instantly.

*Important Notes*
- PHP *cURL* must be enabled (used for HTTP calls).  
- The extension only works for *starred entries*; un-starring does nothing.  
- Missing API key, user ID, or collection key will cause the extension to log a warning and skip export.  
- Zotero API limits apply – ensure your API key has write permission for the target library.  
- Optional translation server must accept plain-text URLs at =<base>/web= and return a JSON array of Zotero items (see Zotero’s Translation Server docs).  
