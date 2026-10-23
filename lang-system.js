// Fixed Language System for Naxos Balcony Apartments - SIMPLE VERSION

// Translations object - SIMPLE VERSION
const translations = {
    el: {
        // Navigation
        home: "Αρχική",
        gallery: "Φωτογραφίες",
        amenities: "Παροχές",
        weather: "Live Weather",
        contact: "Επικοινωνία",
        callUs: "ΚΑΛΕΣΤΕ ΜΑΣ",

        // Hero section
        heroTitle: "Naxos Balcony Apartments",
        heroSubtitle: "Διαμονή με Θέα στο Γλινάδο",
        heroDescription: "Απολαύστε την απόλυτη ηρεμία και το ομορφότερο ηλιοβασίλεμα της Νάξου",

        // Intro section
        welcomeTitle: "Καλώς ήρθατε",
        welcomeText: "Καλώς ήρθατε στο Naxos Balcony Apartments, το δικό σας ησυχαστήριο στο γραφικό Γλινάδο της Νάξου. Τα πλήρως εξοπλισμένα δωμάτιά μας συνδυάζουν την παραδοσιακή κυκλαδίτικη φιλοξενία με σύγχρονες ανέσεις και πανοραμική θέα στο Αιγαίο. Το κατάλυμά μας βρίσκεται σε προνομιακό, κομβικό σημείο του νησιού, προσφέροντας την ιδανική ισορροπία ανάμεσα στην ηρεμία και την άμεση πρόσβαση. Σε μόλις λίγα λεπτά βρίσκεστε στη Χώρα της Νάξου, καθώς και σε μερικές από τις πιο όμορφες παραλίες του νησιού. Απολαύστε την αυθεντική εμπειρία διακοπών, μακριά από τον θόρυβο αλλά κοντά σε όλα.",

        // Naxos & Glinado section
        naxosTitle: "Η Νάξος & Το Γλινάδο",
        naxosSubtitle: "Ανακαλύψτε τη μαγεία του νησιού και την ηρεμία του παραδοσιακού χωριού μας",

        // Card titles
        glinadoTitle: "Γλινάδο Νάξου",
        attractionsTitle: "Αξιοθέατα Νάξου",
        activitiesTitle: "Δραστηριότητες & Εμπειρίες",

        // Glinado card bullet points - LABELS
        glinadoLocationLabel: "Παραδοσιακή τοποθεσία:",
        glinadoTranquilityLabel: "Γαλήνη & Ηρεμία:",
        glinadoViewLabel: "Πανέμορφη θέα:",
        glinadoConvenienceLabel: "Ευκολία:",
        glinadoHubLabel: "Ορμητήριο:",
        glinadoBeachesLabel: "Κοντινές παραλίες:",
        glinadoTasteLabel: "Τοπική γεύση:",
        // Glinado card bullet points - CONTENT
        glinadoLocation: "Μόλις 5 λεπτά (5χλμ) από το λιμάνι και τη Χώρα της Νάξου",
        glinadoTranquility: "Μακριά από τον τουριστικό θόρυβο, στην καρδιά της ναξιώτικης φύσης",
        glinadoView: "Πανοραμική ματιά στο Αιγαίο Πέλαγος, την Πάρο και τα γύρω νησιά.",
        glinadoConvenience: "Σε κοντινή απόσταση θα βρείτε Mini Market, φαρμακείο και στάση λεωφορείου για τις μετακινήσεις σας.",
        glinadoHub: "Η θέση του χωριού επιτρέπει την ταχύτατη πρόσβαση τόσο στις ακτές όσο και στην ορεινή Νάξο.",
        glinadoBeaches: "Άγιος Προκόπιος (12'), Αγία Άννα (9'), Άγιος Γεώργιος (11'), Αλυκό (18'), Μικρή Βίγλα (20') και Χαβάη (15')",
        glinadoTaste: "Αυθεντικές ταβέρνες και ο παραδοσιακός φούρνος του χωριού με φρέσκα τοπικά προϊόντα",

        // Attractions card bullet points - LABELS
        attractionsPortaraLabel: "Πόρταρα:",
        attractionsCastleLabel: "Κάστρο της Νάξου:",
        attractionsTownLabel: "Χώρα Νάξου:",
        attractionsBeachesLabel: "Παραλίες:",
        attractionsMountainLabel: "Ορεινή Νάξος:",
        attractionsYriaLabel: "Αρχαιολογικός Χώρος Ύρια:",
        attractionsKourosLabel: "Οι Κούροι της Νάξου:",
        
        // Attractions card bullet points - CONTENT
        attractionsPortara: "Το εμβληματικό μαρμάρινο μνημείο (12 λεπτά 5,5 km)",
        attractionsCastle: "Μεσαιωνική πόλη (12 λεπτά)",
        attractionsTown: "Παραδοσιακά σοκάκια και καταστήματα (περίπου 12 λεπτά)",
        attractionsBeaches: "Πλάκα, Άγιος Προκόπιος, Αγία Άννα, Μικρή Βίγλα, Αλυκό & Χαβάη (10-20 λεπτά)",
        attractionsMountain: "Τα γραφικά χωριά Απείρανθος, Φιλώτι Κόρωνος (40'), και το Χαλκί (20') για το περίφημο λικέρ Κίτρο και παραδοσιακά γλυκά",
        attractionsYria: "Ο εντυπωσιακός αρχαίος ναός του Διονύσου, μόλις 10 λεπτά από το κατάλυμα",
        attractionsKouros: "Επισκεφθείτε τα επιβλητικά αρχαία αγάλματα στις Μέλανες (15') και στον Απόλλωνα (53')",

        // Activities card bullet points - LABELS
        activitiesWatersportsLabel: "Θαλάσσια σπορ:",
        activitiesHikingLabel: "Φύση & Περιπέτεια:",
        activitiesGastronomyLabel: "Γαστρονομία:",
        activitiesWineLabel: "Οινογνωσία:",
        activitiesFestivalsLabel: "Παραδοσιακά πανηγύρια:",
        activitiesSunsetsLabel: "Ηλιοβασιλέματα:",
        activitiesSailingLabel: "Ιστιοπλοΐα & Σκάφος:",
        
        // Activities card bullet points - CONTENT
        activitiesWatersports: "windsurfing, kitesurfing, diving",
        activitiesHiking: "Ορεινές βόλτες, πεζοπορία σε αρχαία μονοπάτια και ποδηλασία βουνού",
        activitiesGastronomy: "Γευσιγνωσία τοπικών προϊόντων, όπως η περίφημη Γραβιέρα Νάξου, οι διάσημες Πατάτες και το παραδοσιακό λικέρ Κίτρο",
        activitiesWine: "Επισκεφθείτε τοπικά οινοποιεία για να δοκιμάσετε τις εκλεκτές ποικιλίες ναξιώτικου κρασιού και αποστάγματα.",
        activitiesFestivals: "Ζήστε την αυθεντική ναξιώτικη διασκέδαση με βιολιά και τοπικούς χορούς κατά τους καλοκαιρινούς μήνες.",
        activitiesSunsets: "Μοναδικές στιγμές με θέα τον ήλιο να χάνεται στο Αιγαίο, απευθείας από το κατάλυμά μας",
        activitiesSailing: "Ημερήσιες εκδρομές με ιστιοπλοϊκό ή ταχύπλοο στις Μικρές Κυκλάδες και σε δυσπρόσιτες παραλίες",

        // Rooms section
        roomsTitle: "Τα Δωμάτιά μας",
        room3Title: "Room 3 (26 τ.μ.)",
        room3Desc: "Άνετο και αυτόνομο δωμάτιο 26 τ.μ., ιδανικό για 2 άτομα. Διαθέτει πλήρως εξοπλισμένη κουζίνα και ιδιωτικό μπαλκόνι για στιγμές χαλάρωσης σε μια ήσυχη γωνιά του συγκροτήματος.",
        room4Title: "Room 4 (26 τ.μ.)",
        room4Desc: "Ευρύχωρο διαμέρισμα 26 τ.μ. που προσφέρει πλήρη αυτονομία με κουζίνα. Απολαύστε τον πρωινό σας καφέ στο ιδιωτικό μπαλκόνι με θέα το Αιγαίο και τη γαλήνη του ναξιώτικου τοπίου.",
        room5Title: "Room 5 (23 τ.μ. - Η Καλύτερη Θέα)",
        room5Desc: "Φωτεινό studio 23 τ.μ. με προνομιακή θέση και πανοραμική, ανεμπόδιστη θέα στο Αιγαίο. Συνδυάζει άνεση με το ομορφότερο ηλιοβασίλεμα στο Γλινάδο.",
        room6Title: "Room 6 (26 τ.μ. - Η Καλύτερη Θέα)",
        room6Desc: "Αυτόνομο διαμέρισμα 26 τ.μ. για 2 άτομα, που ξεχωρίζει για την εκπληκτική θέα στη θάλασσα. Με πλήρη εξοπλισμό κουζίνας και μεγάλους χώρους, αποτελεί το ιδανικό καταφύγιο.",

        // Footer
        address: "Γλινάδο, Νάξος, Κυκλάδες",
        license: "Αριθμός Γνωστοποίησης: 1174Κ13000166800",
        
        // Gallery page
        galleryTitle: "Φωτογραφίες",
        gallerySubtitle: "Room 3 • Room 4 • Room 5 • Room 6 • Εξωτερικοί χώροι • Ηλιοβασίλεμα",
        galleryAll: "Όλες",
        galleryRoom3: "Room 3",
        galleryRoom4: "Room 4",
        galleryRoom5: "Room 5",
        galleryRoom6: "Room 6",
        galleryExterior: "Εξωτερικοί χώροι",
        gallerySunset: "Ηλιοβασίλεμα",
        
        // ========== PAGE TITLES ==========
        pageTitleHome: "Naxos Balcony Apartments | Διαμονή με Θέα στο Γλινάδο Νάξου",
        pageTitleGallery: "Φωτογραφίες | Naxos Balcony Apartments",
        pageTitleAmenities: "Παροχές & Ανέσεις | Naxos Balcony Apartments",
        pageTitleWeather: "Live Weather | Naxos Balcony Apartments",
        pageTitleContact: "Επικοινωνία & Τοποθεσία | Naxos Balcony Apartments",

        // ========== CONTACT PAGE ==========
        contactTitle: "Επικοινωνία & Τοποθεσία",
        contactSubtitle: "Βρείτε μας εύκολα στο Γλινάδο — καλέστε, στείλτε email ή ανοίξτε οδηγίες στο Google Maps",
        contactSection1: "Επικοινωνία",
        contactDesc: "Είμαστε εδώ για να σας βοηθήσουμε με πληροφορίες και διαθεσιμότητα.",
        contactPhoneLabel: "Τηλέφωνο",
        contactWhatsAppLabel: "WhatsApp & Viber",
        contactEmailLabel: "Email",
        contactCallButton: "Κλήση",
        contactWhatsAppButton: "WhatsApp",
        contactEmailButton: "Email",
        contactSocialTitle: "Ακολουθήστε μας & Δείτε κριτικές",
        contactDirectionsTitle: "Οδηγίες & Πρόσβαση",
        contactLocationLabel: "Τοποθεσία:",
        contactDistanceLabel: "Απόσταση από Λιμάνι/Χώρα:",
        contactParkingLabel: "Πάρκινγκ:",
        contactMapsButton: "Άνοιγμα στο Google Maps",
        contactNoteLabel: "Σημείωση:",
        contactNoteContent: "Διαθέτουμε σύστημα Self Check-in. Θα λάβετε τις οδηγίες πρόσβασης και τον κωδικό για την κάρτα-κλειδί σας λίγο πριν την άφιξή σας.",

        // ========== WEATHER PAGE ==========
        weatherTitle: "Ο Καιρός στο Γλινάδο Νάξου – Live Μετεωρολογικός Σταθμός",
        weatherSubtitle: "Δείτε τις καιρικές συνθήκες στο Γλινάδο σε πραγματικό χρόνο, απευθείας από τον ιδιωτικό μετεωρολογικό σταθμό του Naxos Balcony Apartments. Ενημερωθείτε έγκυρα για τον καιρό στη Νάξο πριν την εξόρμησή σας στις παραλίες.",
        weatherSectionTitle: "Ο καιρός στο κατάλυμά μας",
        weatherDesc: "Ακριβή δεδομένα για τη θερμοκρασία, τον άνεμο και την υγρασία, ιδανικά για να προγραμματίσετε το μπάνιο σας στις κοντινές παραλίες μας",
        weatherTemp: "Θερμοκρασία",
        weatherHumidity: "Υγρασία",
        weatherWindSpeed: "Ταχύτητα Ανέμου",
        weatherWindDir: "Κατεύθυνση Ανέμου",
        weatherPressure: "Ατμοσφαιρική Πίεση",
        weatherUV: "Δείκτης UV",
        weatherStationLabel: "Σταθμός:",
        weatherLastLabel: "Τελευταία μέτρηση:",
        weatherStatusOn: "Σε λειτουργία (Live)",
        weatherStatusOff: "Εκτός λειτουργίας",
        weatherTipsTitle: "Χρήσιμες συμβουλές για τις παραλίες μας",
        weatherTipsWindTitle: "💨 Οδηγός Ανέμων Νάξου: Πού να κολυμπήσετε",
        weatherTipsWindContent: "Βόρειοι Άνεμοι (Μελτέμια): Αν ο σταθμός δείχνει Β/ΒΔ άνεμο, οι παραλίες Άγιος Προκόπιος και Αγία Άννα είναι οι ιδανικές, καθώς παραμένουν απάνεμες.\n\nΝότιοι Άνεμοι (Όστρια): Προτιμήστε τις παραλίες στη βόρεια πλευρά (π.χ. Αμπράμ, Αμίτης) ή την περιοχή της Χώρας.",
        weatherTipsSunTitle: "☀️ Προστασία από τον Ήλιο:",
        weatherTipsSunContent: "Παρακολουθήστε τον Δείκτη UV. Όταν η τιμή είναι πάνω από 6, η ηλιοθεραπεία στο Naxos Balcony Apartments ή στην παραλία απαιτεί υψηλή προστασία.",

        // ========== AMENITIES PAGE ==========
        amenitiesTitle: "Παροχές & Ανέσεις",
        amenitiesSubtitle: "Όλα όσα χρειάζεστε για μια άνετη και αυτόνομη διαμονή στο Γλινάδο της Νάξου",
        amenitiesBasicFeatures: "Βασικά χαρακτηριστικά καταλύματος",
        amenitiesPill1: "Self Check-in",
        amenitiesPill2: "Δωρεάν Ιδιωτικό Parking",
        amenitiesPill3: "Δωρεάν Wi‑Fi σε όλους τους χώρους",
        amenitiesPill4: "Live δεδομένα καιρού (Ιδιωτικός Σταθμός)",
        amenitiesPill5: "Δωμάτια για μη καπνίζοντες",
        amenitiesPill6: "Δεν επιτρέπονται κατοικίδια",
        amenitiesKitchen: "Κουζίνα",
        amenitiesExterior: "Εξωτερικοί Χώροι & Θέα",
        amenitiesBedroom: "Υπνοδωμάτιο & Άνεση",
        amenitiesBathroom: "Μπάνιο & Περιποίηση",
        amenitiesTechnology: "Τεχνολογία & Πληροφόρηση",
        amenitiesParking: "Parking & Υπηρεσίες",
        amenitiesCheckin: "Self Check-in & Άφιξη",
        amenitiesSecurity: "Ασφάλεια & Προστασία",
        amenitiesLanguages: "Γλώσσες επικοινωνίας",
        amenitiesKitchenDesc: "Ιδανικό για μακροχρόνιες διαμονές: Τα δωμάτιά μας διαθέτουν όλα τα απαραίτητα σκεύη.",
        amenitiesKitchen1: "Πλήρως εξοπλισμένη κουζίνα με εστίες και φούρνο",
        amenitiesKitchen2: "Ψυγείο",
        amenitiesKitchen3: "Συσκευή καφέ",
        amenitiesKitchen4: "Μαγειρικά σκεύη και πλήρες σετ σερβιρίσματος",
        amenitiesKitchen5: "Τραπέζι φαγητού",
        amenitiesExterior1: "Πανοραμική θέα στη θάλασσα και το Αιγαίο",
        amenitiesExterior2: "Ιδιωτικό μπαλκόνι με έπιπλα εξωτερικού χώρου",
        amenitiesExterior3: "Ηλιόλουστη βεράντα για χαλάρωση",
        amenitiesExterior4: "Θέα στον κήπο και την εσωτερική αυλή",
        amenitiesBedroom1: "Λευκά είδη και ντουλάπα",
        amenitiesBedroom2: "Αυτόνομος κλιματισμός (A/C)",
        amenitiesBedroom3: "Ηχομονωμένα δωμάτια",
        amenitiesBedroom4: "Δάπεδο από πλακάκι",
        amenitiesBedroom5: "Πρίζες κοντά στο κρεβάτι και απλώστρα ρούχων",
        amenitiesBathroom1: "Ιδιωτικό μπάνιο με ντους",
        amenitiesBathroom2: "Δωρεάν προϊόντα περιποίησης και στεγνωτήρας μαλλιών",
        amenitiesBathroom3: "Φρεσκοπλυμένες πετσέτες και μπουρνούζια",
        amenitiesBathroom4: "Χαρτί υγείας",
        amenitiesTech1: "Live Weather: Ιδιωτικός μετεωρολογικός σταθμός με ζωντανά δεδομένα",
        amenitiesTech2: "Smart TV επίπεδης οθόνης με υπηρεσίες streaming (Netflix)",
        amenitiesTech3: "Δωρεάν Wi-Fi υψηλής ταχύτητας",
        amenitiesParkingDesc: "Χώρος Στάθμευσης: Ιδιωτικός χώρος στάθμευσης δωρεάν",
        amenitiesCheckin1: "Αυτόνομη άφιξη (Self Check-in): Η πρόσβαση γίνεται εύκολα με κάρτα-κλειδί",
        amenitiesCheckin2: "Ευελιξία: Γρήγορη και ιδιωτική διαδικασία check-in/out",
        amenitiesCheckin3: "Τιμολόγηση: Δυνατότητα έκδοσης τιμολογίου",
        amenitiesSecurity1: "Πρόσβαση με κάρτα-κλειδί: Για την ασφάλεια και την ευκολία σας",
        amenitiesSecurity2: "Ανιχνευτές καπνού και πυροσβεστήρες",
        amenitiesSecurity3: "Κάμερες ασφαλείας κλειστού κυκλώματος σε κοινόχρηστους χώρους",
        amenitiesLanguagesDesc: "Ελληνικά, Αγγλικά",
        amenitiesLangGreek: "Ελληνικά",
        amenitiesLangEnglish: "Αγγλικά",
        phone: "📞 6942279665",
        email: "📧 naxosbalcony@gmail.com",
    },

    en: {
        // Navigation
        home: "Home",
        gallery: "Gallery",
        amenities: "Amenities",
        weather: "Live Weather",
        contact: "Contact",
        callUs: "CALL US",

        // Hero section
        heroTitle: "Naxos Balcony Apartments",
        heroSubtitle: "Accommodation with View in Glinado",
        heroDescription: "Enjoy absolute tranquility and the most beautiful sunset in Naxos",

        // Intro section
        welcomeTitle: "Welcome",
        welcomeText: "Welcome to Naxos Balcony Apartments, your own retreat in the picturesque Glinado of Naxos. Our fully equipped rooms combine traditional Cycladic hospitality with modern comforts and panoramic views of the Aegean Sea. Our accommodation is located at a privileged, key point of the island, offering the ideal balance between tranquility and immediate access. Within just a few minutes, you can reach Naxos Town, as well as some of the island's most beautiful beaches. Enjoy an authentic holiday experience, away from the noise but close to everything.",

        // Naxos & Glinado section
        naxosTitle: "Naxos & Glinado",
        naxosSubtitle: "Discover the magic of the island and the tranquility of our traditional village",

        // Card titles
        glinadoTitle: "Glinado Naxos",
        attractionsTitle: "Naxos Attractions",
        activitiesTitle: "Activities & Experiences",

        // Glinado card bullet points - LABELS
        glinadoLocationLabel: "Traditional location:",
        glinadoTranquilityLabel: "Peace & Tranquility:",
        glinadoViewLabel: "Beautiful view:",
        glinadoConvenienceLabel: "Convenience:",
        glinadoHubLabel: "Hub:",
        glinadoBeachesLabel: "Nearby beaches:",
        glinadoTasteLabel: "Local taste:",
        
        // Glinado card bullet points - CONTENT
        glinadoLocation: "Just 5 minutes (5km) from the port and Naxos Town",
        glinadoTranquility: "Away from tourist noise, in the heart of Naxian nature",
        glinadoView: "Panoramic view of the Aegean Sea, Paros and surrounding islands.",
        glinadoConvenience: "Within short distance you will find Mini Market, pharmacy and bus stop for your transportation.",
        glinadoHub: "The village's location allows quick access to both the coasts and mountainous Naxos.",
        glinadoBeaches: "Agios Prokopios (12'), Agia Anna (9'), Agios Georgios (11'), Alyko (18'), Mikri Vigla (20') and Hawaii (15')",
        glinadoTaste: "Authentic taverns and the village's traditional bakery with fresh local products",

        // Attractions card bullet points - LABELS
        attractionsPortaraLabel: "Portara",
        attractionsCastleLabel: "Naxos Castle",
        attractionsTownLabel: "Naxos Town",
        attractionsBeachesLabel: "Beaches:",
        attractionsMountainLabel: "Mountainous Naxos:",
        attractionsYriaLabel: "Yria Archaeological Site:",
        attractionsKourosLabel: "The Kouros of Naxos:",
        
        // Attractions card bullet points - CONTENT
        attractionsPortara: "Portara - The iconic marble monument (12 minutes 5.5 km)",
        attractionsCastle: "Naxos Castle - Medieval town (12 minutes)",
        attractionsTown: "Naxos Town - Traditional alleys and shops (about 12 minutes)",
        attractionsBeaches: "Plaka, Agios Prokopios, Agia Anna, Mikri Vigla, Alyko & Hawaii (10-20 minutes)",
        attractionsMountain: "Apiranthos, Filoti, Koronos (40'), and Chalki (20') for the famous Kitro liqueur",
        attractionsYria: "The impressive ancient temple of Dionysus, just 10 minutes away",
        attractionsKouros: "Visit the imposing ancient statues in Melanes (15') and Apollo (53')",

        // Activities card bullet points - LABELS
        activitiesWatersportsLabel: "Watersports:",
        activitiesHikingLabel: "Nature & Adventure:",
        activitiesGastronomyLabel: "Gastronomy:",
        activitiesWineLabel: "Wine tasting:",
        activitiesFestivalsLabel: "Traditional festivals:",
        activitiesSunsetsLabel: "Sunsets:",
        activitiesSailingLabel: "Sailing & Boat:",
        
        // Activities card bullet points - CONTENT
        activitiesWatersports: "windsurfing, kitesurfing, diving",
        activitiesHiking: "Mountain treks, hiking on ancient trails and mountain biking",
        activitiesGastronomy: "Tasting of local products, such as Naxos Graviera cheese and the renowned Potatoes",
        activitiesWine: "Visit local wineries to taste the exquisite varieties of Naxian wine.",
        activitiesFestivals: "Experience authentic Naxian entertainment with violins and local dances.",
        activitiesSunsets: "Unique moments watching the sun disappear into the Aegean from our balcony",
        activitiesSailing: "Day trips by sailboat or speedboat to the Small Cyclades and remote beaches",

        // Rooms section
        roomsTitle: "Our Rooms",
        room3Title: "Room 3 (26 sq.m.)",
        room3Desc: "Comfortable and autonomous 26 sq.m. room, ideal for 2 people. Features a fully equipped kitchen and private balcony.",
        room4Title: "Room 4 (26 sq.m.)",
        room4Desc: "Spacious apartment with full kitchen. Enjoy your morning coffee on the private balcony with view of the Aegean Sea.",
        room5Title: "Room 5 (23 sq.m. - Best View)",
        room5Desc: "Bright studio with panoramic, unobstructed view of the Aegean Sea and the most beautiful sunset in Glinado.",
        room6Title: "Room 6 (26 sq.m. - Best View)",
        room6Desc: "Autonomous apartment for 2 people with stunning sea view and full kitchen equipment.",

        // Footer
        address: "Glinado, Naxos, Cyclades",
        license: "Notification Number: 1174Κ13000166800",
        
        // Gallery page
        galleryTitle: "Gallery",
        gallerySubtitle: "Room 3 • Room 4 • Room 5 • Room 6 • Exterior Areas • Sunset",
        galleryAll: "All",
        galleryRoom3: "Room 3",
        galleryRoom4: "Room 4",
        galleryRoom5: "Room 5",
        galleryRoom6: "Room 6",
        galleryExterior: "Exterior Areas",
        gallerySunset: "Sunset",

        // ========== PAGE TITLES ==========
        pageTitleHome: "Naxos Balcony Apartments | Accommodation with View in Glinado Naxos",
        pageTitleGallery: "Gallery | Naxos Balcony Apartments",
        pageTitleAmenities: "Amenities & Facilities | Naxos Balcony Apartments",
        pageTitleWeather: "Live Weather | Naxos Balcony Apartments",
        pageTitleContact: "Contact & Location | Naxos Balcony Apartments",

        // ========== CONTACT PAGE ==========
        contactTitle: "Contact & Location",
        contactSubtitle: "Find us easily in Glinado — call, send email or open directions on Google Maps",
        contactSection1: "Contact",
        contactDesc: "We are here to help you with information and availability.",
        contactPhoneLabel: "Phone",
        contactWhatsAppLabel: "WhatsApp & Viber",
        contactEmailLabel: "Email",
        contactCallButton: "Call",
        contactWhatsAppButton: "WhatsApp",
        contactEmailButton: "Email",
        contactSocialTitle: "Follow us & See reviews",
        contactDirectionsTitle: "Directions & Access",
        contactLocationLabel: "Location:",
        contactDistanceLabel: "Distance from Port/Town:",
        contactParkingLabel: "Parking:",
        contactMapsButton: "Open in Google Maps",
        contactNoteLabel: "Note:",
        contactNoteContent: "We have a Self Check-in system. You will receive instructions before arrival.",

        // ========== WEATHER PAGE ==========
        weatherTitle: "Live Weather in Glinado, Naxos – Real-Time Station",
        weatherSubtitle: "Check the live weather conditions in Glinado, Naxos, directly from our private station. Get real-time data perfect for planning your day at the beach.",
        weatherSectionTitle: "Weather at our accommodation",
        weatherDesc: "Accurate data for temperature, wind and humidity, ideal for planning your swim at nearby beaches",
        weatherTemp: "Temperature",
        weatherHumidity: "Humidity",
        weatherWindSpeed: "Wind Speed",
        weatherWindDir: "Wind Direction",
        weatherPressure: "Atmospheric Pressure",
        weatherUV: "UV Index",
        weatherStationLabel: "Station:",
        weatherLastLabel: "Last measurement:",
        weatherStatusOn: "In operation (Live)",
        weatherStatusOff: "Out of service",
        weatherTipsTitle: "Useful tips for our beaches",
        weatherTipsWindTitle: "💨 Naxos Wind Guide: Best Beaches for Swimming",
        weatherTipsWindContent: "North Winds (Meltemi): When the station shows a N/NW wind, the beaches of Agios Prokopios and Agia Anna are the best choices, as they remain calm.\n\nSouth Winds: In case of South winds, we recommend visiting the beaches on the northern side of the island or Naxos Chora.",
        weatherTipsSunTitle: "☀️ Sun Protection:",
        weatherTipsSunContent: "Check our UV Index. When the value is above 6, sunbathing at Naxos Balcony or the beach requires high protection.",

        // ========== AMENITIES PAGE ==========
        amenitiesTitle: "Amenities & Facilities",
        amenitiesSubtitle: "Everything you need for a comfortable stay in Glinado, Naxos",
        amenitiesBasicFeatures: "Basic features",
        amenitiesPill1: "Self Check-in",
        amenitiesPill2: "Free Private Parking",
        amenitiesPill3: "Free Wi‑Fi in all areas",
        amenitiesPill4: "Live weather data",
        amenitiesPill5: "Non-smoking rooms",
        amenitiesPill6: "Pets not allowed",
        amenitiesKitchen: "Kitchen",
        amenitiesExterior: "Exterior Areas & View",
        amenitiesBedroom: "Bedroom & Comfort",
        amenitiesBathroom: "Bathroom & Care",
        amenitiesTechnology: "Technology & Information",
        amenitiesParking: "Parking & Services",
        amenitiesCheckin: "Self Check-in & Arrival",
        amenitiesSecurity: "Security & Protection",
        amenitiesLanguages: "Communication Languages",
        amenitiesKitchenDesc: "Ideal for long-term stays: Our rooms have all the necessary utensils.",
        amenitiesKitchen1: "Fully equipped kitchen with hobs and oven",
        amenitiesKitchen2: "Refrigerator",
        amenitiesKitchen3: "Coffee maker",
        amenitiesKitchen4: "Cooking utensils and complete serving set",
        amenitiesKitchen5: "Dining table",
        amenitiesExterior1: "Panoramic view of the sea and the Aegean",
        amenitiesExterior2: "Private balcony with outdoor furniture",
        amenitiesExterior3: "Sunny veranda for relaxation",
        amenitiesExterior4: "View of the garden and inner courtyard",
        amenitiesBedroom1: "White linens and wardrobe",
        amenitiesBedroom2: "Autonomous air conditioning (A/C)",
        amenitiesBedroom3: "Soundproofed rooms",
        amenitiesBedroom4: "Tile flooring",
        amenitiesBedroom5: "Sockets near the bed",
        amenitiesBathroom1: "Private bathroom with shower",
        amenitiesBathroom2: "Free care products and hair dryer",
        amenitiesBathroom3: "Freshly washed towels and bathrobes",
        amenitiesBathroom4: "Toilet paper",
        amenitiesTech1: "Live Weather: Private weather station with live data",
        amenitiesTech2: "Smart flat-screen TV with Netflix",
        amenitiesTech3: "Free high-speed Wi-Fi",
        amenitiesParkingDesc: "Parking Space: Private free parking within the accommodation",
        amenitiesCheckin1: "Self Check-in: Access easily with a key card",
        amenitiesCheckin2: "Flexibility: Quick and private process",
        amenitiesCheckin3: "Invoicing: Possibility for businesses",
        amenitiesSecurity1: "Access with key card",
        amenitiesSecurity2: "Smoke detectors and fire extinguishers",
        amenitiesSecurity3: "Security cameras in common areas",
        amenitiesLanguagesDesc: "Greek, English",
        amenitiesLangGreek: "Greek",
        amenitiesLangEnglish: "English",
        phone: "📞 6942279665",
        email: "📧 naxosbalcony@gmail.com",
    }
};

// Language system functions
let currentLang = 'el';

function setLanguage(lang) {
    console.log("Setting language to:", lang);
    currentLang = lang;
    updateContent(lang);
    updateLanguageButtons(lang);
    localStorage.setItem('preferredLang', lang);
}

function updateLanguageButtons(lang) {
    const elButton = document.getElementById('langEl');
    const enButton = document.getElementById('langEn');

    if (elButton && enButton) {
        elButton.style.fontWeight = 'normal';
        elButton.style.opacity = '0.7';
        enButton.style.fontWeight = 'normal';
        enButton.style.opacity = '0.7';

        if (lang === 'el') {
            elButton.style.fontWeight = 'bold';
            elButton.style.opacity = '1';
        } else {
            enButton.style.fontWeight = 'bold';
            enButton.style.opacity = '1';
        }
    }
}

function updateContent(lang) {
    console.log("Updating content for language:", lang);
    const t = translations[lang];
    if (!t) {
        console.error("No translations found for language:", lang);
        return;
    }

    const elements = document.querySelectorAll('[data-i18n]');
    console.log("Found", elements.length, "elements with data-i18n attribute");

    elements.forEach(element => {
        const key = element.getAttribute('data-i18n');
        if (t[key]) {
            if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
                element.value = t[key];
            } else {
                element.textContent = t[key];
            }
            console.log("Updated element with key:", key, "to:", t[key].substring(0, 50) + "...");
        } else {
            console.warn("No translation found for key:", key);
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const savedLang = localStorage.getItem('preferredLang');
    if (savedLang && (savedLang === 'el' || savedLang === 'en')) {
        setLanguage(savedLang);
    } else {
        setLanguage('el');
    }
    
    const elButton = document.getElementById('langEl');
    const enButton = document.getElementById('langEn');
    
    if (elButton) {
        elButton.addEventListener('click', function() {
            setLanguage('el');
        });
    }
    
    if (enButton) {
        enButton.addEventListener('click', function() {
            setLanguage('en');
        });
    }
});