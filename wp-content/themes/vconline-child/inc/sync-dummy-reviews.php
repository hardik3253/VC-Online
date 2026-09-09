<?php
/**
 * Automated database synchronization for realistic dummy student names and expanded 3-4 line reviews.
 * This runs automatically on live site once when code is pulled via git.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'init', 'vca_run_reviews_and_students_sync' );

function vca_run_reviews_and_students_sync() {
    $force = isset( $_GET['vca_sync_reviews'] ) && '1' === sanitize_text_field( $_GET['vca_sync_reviews'] );

    if ( get_option( 'vca_dummy_reviews_synced_v2' ) && ! $force ) {
        return;
    }

    global $wpdb;

    $students = [
        1  => ['name' => 'Ananya Sharma',    'first' => 'Ananya',    'last' => 'Sharma',    'fallback_id' => 418],
        2  => ['name' => 'Rohit Verma',      'first' => 'Rohit',     'last' => 'Verma',     'fallback_id' => 419],
        3  => ['name' => 'Priya Patel',      'first' => 'Priya',     'last' => 'Patel',     'fallback_id' => 420],
        4  => ['name' => 'Rohan Kapoor',     'first' => 'Rohan',     'last' => 'Kapoor',    'fallback_id' => 421],
        5  => ['name' => 'Sneha Joshi',      'first' => 'Sneha',     'last' => 'Joshi',     'fallback_id' => 422],
        6  => ['name' => 'Kunal Malhotra',   'first' => 'Kunal',     'last' => 'Malhotra',  'fallback_id' => 423],
        7  => ['name' => 'Ritu Deshmukh',    'first' => 'Ritu',      'last' => 'Deshmukh',  'fallback_id' => 424],
        8  => ['name' => 'Aditya Singhania', 'first' => 'Aditya',    'last' => 'Singhania', 'fallback_id' => 425],
        9  => ['name' => 'Tanvi Nair',       'first' => 'Tanvi',     'last' => 'Nair',      'fallback_id' => 426],
        10 => ['name' => 'Vikram Chopra',    'first' => 'Vikram',    'last' => 'Chopra',    'fallback_id' => 427],
        11 => ['name' => 'Divya Iyer',       'first' => 'Divya',     'last' => 'Iyer',      'fallback_id' => 431],
        12 => ['name' => 'Amit Sen',         'first' => 'Amit',      'last' => 'Sen',       'fallback_id' => 432],
        13 => ['name' => 'Neha Bansal',      'first' => 'Neha',      'last' => 'Bansal',    'fallback_id' => 433],
        14 => ['name' => 'Karan Mehra',      'first' => 'Karan',     'last' => 'Mehra',     'fallback_id' => 434],
        15 => ['name' => 'Pooja Rao',        'first' => 'Pooja',     'last' => 'Rao',       'fallback_id' => 435],
        16 => ['name' => 'Shreya Roy',       'first' => 'Shreya',    'last' => 'Roy',       'fallback_id' => 436],
        17 => ['name' => 'Varun Mehta',      'first' => 'Varun',     'last' => 'Mehta',     'fallback_id' => 437],
        18 => ['name' => 'Simran Kaur',      'first' => 'Simran',    'last' => 'Kaur',      'fallback_id' => 438],
        19 => ['name' => 'Nikhil Bhatt',     'first' => 'Nikhil',    'last' => 'Bhatt',     'fallback_id' => 439],
        20 => ['name' => 'Ishita Jain',      'first' => 'Ishita',    'last' => 'Jain',      'fallback_id' => 440],
        21 => ['name' => 'Harish Pillai',    'first' => 'Harish',    'last' => 'Pillai',    'fallback_id' => 441],
        22 => ['name' => 'Meera Nambiar',    'first' => 'Meera',     'last' => 'Nambiar',   'fallback_id' => 442],
        23 => ['name' => 'Gaurav Sethi',     'first' => 'Gaurav',    'last' => 'Sethi',     'fallback_id' => 443],
        24 => ['name' => 'Kavya Kulkarni',   'first' => 'Kavya',     'last' => 'Kulkarni',  'fallback_id' => 444],
        25 => ['name' => 'Rahul Sengupta',   'first' => 'Rahul',     'last' => 'Sengupta',  'fallback_id' => 445],
        26 => ['name' => 'Anjali Menon',     'first' => 'Anjali',    'last' => 'Menon',     'fallback_id' => 446],
        27 => ['name' => 'Manish Tiwari',    'first' => 'Manish',    'last' => 'Tiwari',    'fallback_id' => 447],
        28 => ['name' => 'Pallavi Joshi',    'first' => 'Pallavi',   'last' => 'Joshi',     'fallback_id' => 448],
        29 => ['name' => 'Siddharth Das',    'first' => 'Siddharth', 'last' => 'Das',       'fallback_id' => 449],
        30 => ['name' => 'Natasha Bhatia',   'first' => 'Natasha',   'last' => 'Bhatia',    'fallback_id' => 450],
    ];

    $resolved_student_ids = [];

    // 1. Sync student names in wp_users, usermeta, and comments
    foreach ( $students as $num => $s ) {
        $name     = $s['name'];
        $first    = $s['first'];
        $last     = $s['last'];
        $fallback = $s['fallback_id'];

        $user = get_user_by( 'login', sprintf( 'demo_student_%02d', $num ) );
        if ( ! $user ) {
            $user = get_user_by( 'login', sprintf( 'demo_student_%d', $num ) );
        }
        if ( ! $user ) {
            $row = $wpdb->get_row( $wpdb->prepare(
                "SELECT ID FROM {$wpdb->users} WHERE display_name = %s OR display_name = %s LIMIT 1",
                sprintf( 'Student %02d', $num ),
                sprintf( 'Student %d', $num )
            ) );
            if ( $row ) {
                $user = get_user_by( 'id', $row->ID );
            }
        }
        if ( ! $user && $fallback ) {
            $user = get_user_by( 'id', $fallback );
        }

        if ( $user ) {
            $resolved_student_ids[ $num ] = $user->ID;

            $wpdb->update(
                $wpdb->users,
                [ 'display_name' => $name ],
                [ 'ID' => $user->ID ]
            );

            update_user_meta( $user->ID, 'first_name', $first );
            update_user_meta( $user->ID, 'last_name', $last );
            update_user_meta( $user->ID, 'nickname', $name );

            $wpdb->update(
                $wpdb->comments,
                [ 'comment_author' => $name ],
                [ 'user_id' => $user->ID ]
            );
        } else {
            $resolved_student_ids[ $num ] = $fallback;
        }

        // Also update comment author if stored as demo_student_XX or Student XX
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->comments} 
             SET comment_author = %s 
             WHERE comment_author IN (%s, %s, %s, %s) AND comment_type = 'tutor_course_rating'",
            $name,
            sprintf( 'demo_student_%02d', $num ),
            sprintf( 'demo_student_%d', $num ),
            sprintf( 'Student %02d', $num ),
            sprintf( 'Student %d', $num )
        ) );
    }

    // 2. Expanded 3-4 line course reviews
    $course_definitions = [
        'complimentary-eyebrow-makeup-course-for-beginners' => [
            'default_id' => 669,
            'reviews' => [
                1 => "This course was exactly what I needed to master eyebrow shaping and styling. The step-by-step breakdown on brow mapping made it so easy to achieve symmetry and clean lines on different face shapes. The tips on choosing the right brow pencil shades and feathering strokes have elevated my everyday makeup work. Truly a fantastic complimentary course for any makeup artist!",
                2 => "As a beginner makeup artist, getting eyebrows right was always the most stressful part for me. The instructor explains brow arches and natural filling techniques with incredible patience and detail. I loved how clearly the tool usage was demonstrated, especially the spoolie and angled brush blending. My client consultations have become so much smoother after practicing these methods.",
                3 => "The lessons in this course are concise, easy to follow, and packed with practical knowledge. Learning how to identify natural brow bone structure before drawing any lines completely changed my technique. The finishing tips for concealing around the brow line give such a crisp, high-definition result. Highly recommend this course to anyone who wants flawless brows!",
                4 => "A wonderful tutorial series that covers everything from basics to pro-level precision. The explanation of hair stroke simulation with ultra-fine pencils was especially insightful and easy to implement. It helped me fix common mistakes like over-filling the inner corners and making brows look too blocky. Thank you VC Online for providing such premium quality education for free!",
                5 => "I was genuinely impressed by how thorough this complimentary course turned out to be. The step-by-step guidance on mapping the start, arch, and tail of the eyebrow according to individual facial features made total sense. My confidence in brow grooming and filling has increased ten-fold. Definitely one of the most useful short makeup courses available online!",
                6 => "Great presentation and very clear close-up camera angles throughout the demonstrations. The instructor highlights subtle details like pressure control, blending out harsh product lines, and setting with clear gel. These small adjustments make a dramatic difference in client photos and real-life appearance. Wonderful resource for both salon professionals and beauty enthusiasts.",
                7 => "The eyebrow shaping techniques taught here are modern, natural, and super flattering. I really appreciated the emphasis on feather-light hair strokes instead of harsh stenciled brows. Pooja Ma'am breaks down product textures—pomades, powders, and pencils—so you know exactly when to use each. Practiced on two clients today and both loved their natural brow definition!",
                8 => "This eyebrow masterclass simplified concepts that I previously found confusing and tricky. The demonstration on correcting asymmetrical brows without making them look artificial was particularly helpful. Every salon apprentice should go through these modules before touching a client's brows. Clear, professional, and straight to the point.",
                9 => "I absolutely loved the practical approach and high production quality of this course. It gives you realistic advice on dealing with sparse brows and creating dimension without piling on heavy product. The brow highlight placement at the arch gave an instant eye-lifting effect in my practice session. Can't believe this high quality training is complimentary!",
                10 => "Excellent tutorial for salon staff looking to upgrade their basic makeup services. The instructor's guidance on hygienic brush handling, brow measurements, and clean skin prep was spot on. Our team has already started applying these brow mapping guidelines during our daily salon appointments. Thank you for this valuable and practical course!",
                11 => "Mastering brows was the missing piece in my makeup routine, and this course solved it perfectly. The tips on softening the head of the eyebrow while defining the tail created such a soft, youthful appearance. The instructor is gentle, articulate, and shows real-time corrections on live models. Highly recommended to everyone aspiring to learn professional makeup!",
                12 => "Very well-structured lessons that deliver genuine salon-ready techniques in minutes. Understanding how brow thickness and arch height influence the overall perception of eye shape was an eye-opener. The course is crisp, engaging, and avoids any unnecessary fluff. Great learning experience from the VC Online team.",
                13 => "Every module in this course is filled with golden tips for clean and polished brow artistry. I especially loved learning how to blend cream pomade with sheer powder to build realistic hair texture. My clients have already noticed how much more natural and defined their eyebrows look. Truly grateful for this complimentary training!",
                14 => "A masterclass in eyebrow styling that every budding makeup artist should watch. The instructor demonstrates both pencil and angled brush techniques with remarkable precision and clarity. It taught me how to work with stubborn brow hair and set them cleanly for all-day hold. Top quality video production and expert guidance throughout.",
                15 => "This course answered all my questions about creating symmetrical brows on challenging face profiles. The step-by-step measurements using nose and eye landmarks made mapping intuitive and quick. I now finish brow styling in half the time without second-guessing my symmetry. Outstanding teaching that delivers immediate practical value!",
                16 => "The clarity and depth in this complimentary eyebrow course exceeded all my expectations. The instructor demonstrates exactly where to start strokes, how much pressure to apply, and how to diffuse pigment seamlessly. It has elevated my portfolio looks and boosted my confidence during client bookings. A big thumbs up to the VC Online academy!",
                17 => "Clear, methodical, and packed with practical insights that salon artists can use every day. The breakdown between everyday natural brows and dramatic editorial brows helped me understand styling flexibility. The close-ups during the feathering application made learning effortless. Highly recommend this course to anyone interested in makeup.",
                18 => "I thoroughly enjoyed this course and finished all modules in one sitting! The emphasis on matching the brow shade to undertones rather than just hair color was a game-changer for me. My brows now look effortlessly lifted and soft instead of harsh and drawn-on. Thank you so much for this amazing complimentary resource!",
                19 => "Very professional training that covers all essentials of eyebrow architecture. The instructor explains common beginner mistakes and shows practical ways to fix over-tweezed or uneven brows. It gave me clear frameworks to use during bridal trials and salon consultations. Brilliant tutorial with high-definition video clarity!",
                20 => "Before taking this course, I always struggled to make both eyebrows look like sisters instead of distant cousins. The brow mapping method taught here made achieving balanced arches so simple and foolproof. The tips on highlighting the brow bone with a subtle matte concealer gave such a clean finish. Extremely grateful for this wonderful course!",
                21 => "Superb guidance on eyebrow grooming, product selection, and precision application. The lessons show real human models with realistic brow challenges, which makes the learning genuinely practical. The pace of the video modules is ideal and easy to follow along with practical kits. Excellent work by the instructor and academy!",
                22 => "This course is a hidden gem for anyone starting their makeup journey. The explanation of feather-touch strokes and using a clean spoolie to soften pigments made an instant difference in my work. My bridal makeup clients loved the soft, modern brow look we created using these steps. Thank you VC Online for this fantastic training!",
                23 => "Extremely detailed, professional, and well-organized makeup tutorial series. The instructor covers every aspect from sanitizing tools to picking proper brow waxes and powders for oily skin types. It has made our salon's makeup consultation process much more standardized. Highly recommended to all beauty professionals!",
                24 => "I learned so many subtle techniques in just under an hour of video lectures. The advice on using micro-strokes to mimic fine brow hairs and blending the top arch cleanly was invaluable. It saved me hundreds of hours of trial and error in my makeup practice. A must-watch masterclass for beginners and pros alike!",
                25 => "Very practical demonstration of modern brow styling trends and client suitability. The camera angles capture every flick of the brush and the pressure variations clearly. It is rare to find complimentary courses that offer this level of professional depth and clarity. Hats off to the VC Online faculty!",
                26 => "The brow mapping and shaping guidelines taught in this course are simply outstanding. I loved how the instructor showed corrective techniques for sparse tails and downward-drooping brow ends. Applying the clear brow setting gel technique gave my models that trending fluffy, laminated effect. Truly an exceptional learning experience!",
                27 => "Solid foundation course for anyone working in beauty salons or doing freelancing. The instructor emphasizes realistic salon timelines, showing how to achieve crisp, symmetrical brows in just five minutes. The audio and video quality are crisp and the instructions are very straightforward. Proud to have completed this training!",
                28 => "Such an informative and delightful course on eyebrow design and makeup definition. The instructor's soothing voice and clear explanations make even intricate styling steps easy to grasp. I now understand exactly how to balance arch height with forehead width for harmony. Thank you VC Online for sharing such expertise freely!",
                29 => "Top-notch quality in both theory and practical demonstration. The discussion on pigment undertones and avoiding ashy or red undertones in brown pencils was particularly enlightening. It has noticeably improved the quality of our salon's client portfolio pictures. Highly recommended for salon technicians and students!",
                30 => "This course made eyebrow styling so effortless and enjoyable to practice. The step-by-step guidance on creating a soft ombré effect from front to tail gave stunning, professional results. My clients are obsessed with how neat yet natural their brows look now. Thank you so much for this amazing complimentary training!"
            ]
        ],

        'shapes-for-face-shapes-complementary-course' => [
            'default_id' => 775,
            'reviews' => [
                1 => "Understanding client face shapes before recommending a haircut has completely transformed my consultations in the salon. The instructor explains the proportions and styling balance with incredible clarity. I can now guide my clients with confidence on which volume and length will suit them best. A must-watch course for both beginners and working hairstylists.",
                2 => "This course provides deep insights into facial geometry that every hair designer needs to know. The way oval, square, round, and heart-shaped faces are broken down with real visual examples is remarkable. It helped me understand why certain haircuts look amazing on one client but fail on another. Highly recommended for every ambitious salon professional!",
                3 => "The consultation techniques taught in this course have boosted my client retention significantly. Showing clients why a specific fringe or layering placement flatters their jawline builds immediate trust. The video quality is crisp and the explanations are easy to absorb and apply immediately on the salon floor. Truly valuable learning!",
                4 => "An eye-opening course that bridges the gap between hair cutting and aesthetic facial harmony. The instructor explains how volume placement at the crown or temples changes the visual balance of facial features. My haircut consultation time has become more productive and enjoyable for my clients. Thank you VC Online for this fantastic complimentary resource!",
                5 => "I never realized how much difference face framing and angle selection could make until I watched this course. Learning how to soften strong jawlines and lengthen rounder facial silhouettes was pure gold. The practical tips can be applied to both women's and men's haircuts effortlessly. Highly recommended to all salon stylists!",
                6 => "Brilliant masterclass on facial anatomy and hair design harmony. The instructor breaks down complex design theory into simple, memorable rules of thumb. It has helped our salon team recommend personalized styles rather than just copying random internet pictures. Top tier professional education!",
                7 => "This course is an essential foundation for anyone who wants to excel as a professional hair designer. The visual diagrams and side-by-side client comparisons made facial proportion analysis super intuitive. My clients appreciate the thoughtful explanations I now give before starting any haircut. A five-star educational experience!",
                8 => "The explanations on balancing cheekbones, forehead width, and jaw angles are world-class. It gave me the vocabulary and confidence to explain haircut choices clearly to high-end salon clientele. Every salon owner should make this mandatory viewing for their styling team. Fantastic training provided by VC Online!",
                9 => "I found this course incredibly helpful for my daily styling and haircutting assignments. The breakdown of heart and diamond face shapes with tailored fringe suggestions resolved many consultation doubts I had. The pacing was engaging and the instructor's expertise shines through every single lesson. Truly appreciate this complimentary offering!",
                10 => "A concise yet powerful course that elevates everyday haircutting into an art of personalization. Understanding where to introduce texture versus where to maintain weight based on facial contours was invaluable. It has directly improved our salon's client satisfaction scores. Excellent guidance and visual demonstrations!",
                11 => "Such a clear and structured course on face shape analysis for hair and beauty consultants. The instructor teaches you how to look past hair texture and focus on bone structure and focal points. I immediately applied the rectangular face framing tips on a client with gorgeous results. Thank you for this brilliant complimentary course!",
                12 => "Very practical, logical, and easy to translate into daily salon operations. The distinction between actual face shape and visual balance created by hair volume was clearly articulated. It has changed the way I assess every new client who sits in my styling chair. Great quality course from start to finish!",
                13 => "This course taught me how to highlight client features and draw attention away from asymmetries naturally. The sections on bangs, parting placements, and jawline-skimming bobs were particularly useful. My confidence in handling indecisive clients has increased ten times over. Highly recommended for salon stylists!",
                14 => "The facial geometry concepts shared in this training are fundamental for any serious hairstylist. It teaches you to look at a haircut as an architectural balance rather than just following steps. The video production is top notch with excellent diagrams and live model demonstrations. Extremely satisfied with the learning!",
                15 => "I loved how simply the instructor explained facial proportions and vertical thirds. Learning to counterbalance long face profiles with horizontal volume and soft waves has worked wonders for my styling clients. This complimentary course delivers value far beyond many paid workshops I have attended. Outstanding work!",
                16 => "A must-watch course for anyone in hairdressing, makeup artistry, or personal styling. The instructor's guidance on combining jawline angles with ear-to-chin guidelines made haircut customization foolproof. My salon clients love that I explain why a cut will look good on them before cutting. Truly fantastic training!",
                17 => "Clear, concise, and full of actionable techniques for salon consultations. The instructor demonstrates how hair length and silhouette can visually slim down or broaden facial appearance. It has helped me communicate with clients with authority and artistic credibility. Thank you VC Online for this great course!",
                18 => "I enjoyed every single module of this face shapes course! The lessons on fringe suitability for square and round faces gave me exact formulas that work every time. The visual aids made remembering proportions effortless even during busy salon hours. Five stars all the way!",
                19 => "Very informative and professional course on client consultations and style mapping. It provides clear diagnostic frameworks to quickly assess face proportions within the first two minutes of a consultation. Our entire salon junior staff has learned so much from these videos. Thank you for sharing this knowledge!",
                20 => "This course made facial proportion analysis so simple and fun to learn! The tips on using side-swept bangs to break angular foreheads and soften features were brilliant. I feel much more equipped to handle diverse client requests and recommend styles that genuinely flatter them. Brilliant course!",
                21 => "Comprehensive guide to facial geometry and haircut design principles. The instructor covers every major face shape with clarity, practical demonstrations, and realistic client scenarios. It helped me refine my graduation and layering placements to flatter individual client jawlines. Highly recommended for all stylists!",
                22 => "Understanding how hair volume and silhouette interact with cheekbones and chin shapes was a game changer for me. The course explains optical illusions created by lines and weight lines clearly. My styling appointments have become much more consultation-focused and professional. Thank you VC Online!",
                23 => "An exceptional training module that every hair academy should incorporate into their syllabus. The clear breakdown of forehead-to-jawline ratios and how parting lines influence visual symmetry was outstanding. It elevated the quality of my everyday haircuts instantly. Hats off to the trainer!",
                24 => "I was amazed at how much practical knowledge was packed into this complimentary course. The instructor provides realistic solutions for clients who want trending cuts that might not naturally suit their face shape. It taught me how to adapt trends to fit each client beautifully. Loved every minute of it!",
                25 => "Superb insights into face shape aesthetics and client consultation strategies. The video explanations are crisp, articulate, and supported by great visual illustrations. It helped me bridge the gap between creative haircutting and client expectations seamlessly. Wonderful work by the VC Online team!",
                26 => "This course gave me the tools to conduct high-end salon consultations with ease. Learning which lengths elongate round faces and which textures soften square contours gave me reliable styling blueprints. My clients are thrilled with the personalized results we are achieving. Highly recommended to everyone!",
                27 => "Practical, engaging, and directly applicable on the salon floor. The instructor explains why one-size-fits-all haircuts do not work and how minor layer adjustments create facial harmony. The video streaming and audio clarity were excellent throughout the course. Very grateful for this training!",
                28 => "A masterclass in client consultation and facial balance that every stylist needs. The tips on adjusting layering around the cheekbones to accentuate eyes and lips were pure gold. I feel confident recommending bespoke haircuts to even my most demanding clients now. Thank you so much VC Online!",
                29 => "Clear, structured, and easy to implement in a busy salon setting. The lessons break down the geometry of hairstyles and how silhouette shapes interact with bone structure. It has significantly improved the confidence and consultation skills of our salon team. Outstanding complimentary course!",
                30 => "I loved this course from start to finish! The visual breakdowns of oval, heart, diamond, and square face shapes made everything click for me. I now conduct my client consultations with complete creative clarity and conviction. Thank you VC Online for offering such high caliber education freely!"
            ]
        ],

        'meo-ri-collection-korean-hair-trends-part-1' => [
            'default_id' => 792,
            'reviews' => [
                1 => "The Korean hair cutting techniques in this series are super trendy and in high demand at our salon. The sectioning and angle control for soft face-framing layers were explained in great detail. I tried the techniques on my clients the very next day and the fluid movement of the hair was stunning. Loving the modern aesthetic and practical approach!",
                2 => "Vipul Sir's explanation of Korean haircut geometry is second to none. Learning how to create lightweight interior graduation while preserving perimeter density completely transformed my long layered cuts. The clients are thrilled with the airy, effortless texture and natural bounce. Highly recommended for every modern stylist!",
                3 => "The curtain bangs and face-framing modules in Part 1 are absolute gold! The instructor demonstrates exact finger angles, elevation, and point cutting depths to achieve that signature soft Korean wave look. The close-up camera angles made following the shear work effortless. This course is worth every single penny!",
                4 => "A brilliant masterclass on modern East Asian hair aesthetics and cutting discipline. The balance between disconnection and seamless blending was demonstrated with crystal clear precision. It has given our salon a massive competitive edge with younger clients who want trending K-pop and K-drama hairstyles. Exceptional training!",
                5 => "I was looking for authentic Korean haircutting education and this course delivered beyond expectations. The way weight removal is handled using slide cutting and texturizing techniques without making the ends look stringy is genius. The resulting movement and softness on clients' hair is breathtaking. Fantastic masterclass!",
                6 => "Outstanding presentation of sectioning patterns and elevation control for Korean styles. The instructor explains the 'why' behind every cut, not just the mechanical steps. My styling speed and precision have noticeably improved after implementing these sectioning blueprints. A must-watch for salon professionals!",
                7 => "The Meo-Ri Collection Part 1 is one of the most practical haircut courses I have taken online. The instruction on creating soft, airy curtain fringe that sweeps back effortlessly was worth the course alone. My clients are loving how easy it is to style these cuts at home. Five stars without hesitation!",
                8 => "The precision and artistic flair demonstrated in this course are truly inspirational. The instructor shows how to tailor the Korean layering approach to diverse hair densities and textures. It has elevated our salon's layered haircut menu and boosted client bookings. Great learning experience from the VC Online team!",
                9 => "Every lesson in Part 1 is packed with subtle hairdressing secrets that make a huge difference in the final look. The techniques for soft feathering around the collarbone and cheekbones created such flattering contours on my models. The video quality and audio clarity made studying a pleasure. Highly recommended!",
                10 => "A world-class haircutting tutorial that bridges international trends with salon-friendly efficiency. The breakdown of tension control when working with dry versus damp hair for Korean styling was an eye-opener. It has helped me achieve consistent, predictable results on every client. Top notch education!",
                11 => "I learned so much about weight distribution and perimeter retention from this course. The demonstration on cutting soft Korean wispy bangs without over-thinning the fringe area was incredibly helpful. My clients love the modern, youthful vibe these haircuts provide. Thank you VC Online for this masterclass!",
                12 => "Very detailed and systematic approach to cutting contemporary layered haircuts. The instructor explains how to adjust cutting angles to prevent the bottom perimeter from becoming too thin or see-through. It has refined my scissor-over-comb and point-cutting skills tremendously. Great course for all hairdressers!",
                13 => "The Korean aesthetic requires such delicate hands and precision, and this course teaches that beautifully. The styling and blowdry methods demonstrated at the end of the cut tied the entire look together seamlessly. My clients are constantly complimenting the natural bounce and lightness of their hair. Loved this course!",
                14 => "Solid haircut architecture and artistic execution throughout the Meo-Ri Part 1 collection. The instructor demonstrates clean sectioning horseshoe patterns and forward over-direction techniques with great mastery. It has given me total clarity on creating customized face-framing layers. An absolute five-star course!",
                15 => "This course completely demystified the trendy Korean layered haircut for me. I used to struggle with blending face framing layers into the longer back perimeter, but the transitional sectioning shown here solved it instantly. The resulting silhouette is soft, commercial, and stunning. Highly recommend this masterclass!",
                16 => "The detail in the video production and the instructor's clear narration made this course an absolute joy. I loved learning how to create movement in straight, heavy hair textures without relying on aggressive thinning shears. My clients have noticed the difference and rebooking rates have soared. Outstanding training!",
                17 => "A masterclass in hair texturizing and modern graduation. The instructor explains the mechanics of hair elevation and how gravity affects the fall of soft layers around the face. The knowledge gained here is immediately applicable in daily salon appointments. Thank you VC Online for another great course!",
                18 => "I thoroughly enjoyed this course and practiced the techniques on three mannequins and two clients! The soft Korean curtain bangs look so chic and effortless, and my clients can easily style them with a simple round brush. The instruction is patient, clear, and inspiring throughout. Five stars!",
                19 => "Exceptional guidance on sectioning, posture, and shear movement for trending haircut designs. The instructor shows how to maintain structural strength in the haircut while achieving maximum surface movement. It has helped our salon stylists deliver premium international looks with complete confidence. Brilliant work!",
                20 => "Before taking this course, my layered haircuts often looked too choppy or disconnected. The seamless blending techniques demonstrated in this Korean trend series gave me the soft, fluid transitions I always wanted. It has boosted my confidence and elevated my haircutting game tremendously. Thank you so much!",
                21 => "Very high quality educational content with genuine salon application. The instructor explains how hair density influences section thickness and cutting angles during Korean haircuts. It has helped me approach thick, coarse hair with a clear, systematic game plan. Highly recommended to all serious hair stylists!",
                22 => "The Meo-Ri collection is fresh, modern, and exactly what young clients are asking for in salons today. The step-by-step demonstration of feathering the front hairline and building soft volume around the crown was fantastic. The finish was airy, touchable, and effortlessly gorgeous. Truly an inspiring course!",
                23 => "Precision haircutting at its best! The instructor's mastery over section geometry and over-direction angles is evident in every clip. It taught me how to eliminate bulky corners without sacrificing hair length or fullness at the bottom. An indispensable resource for any salon stylist aiming for excellence.",
                24 => "I loved how in-depth the instructor went into the theory of Korean hair texture and styling. The tips on maintaining a strong baseline while incorporating wispy, weightless layers around the cheeks gave stunning results on my clients. A phenomenal masterclass that will benefit any hairstylist!",
                25 => "Clear, methodical, and packed with practical insights that elevate everyday haircutting into high fashion. The video clarity allowed me to see the exact finger placement and scissor angles during slide cutting. It has already paid for itself through happy, returning salon clients. Highly recommended!",
                26 => "This course gave me a fresh perspective on layered haircuts and modern fringe design. The instructor explains how to customize Korean curtain bangs according to forehead width and eye placement. My clients are thrilled with how soft and flattering their new hairstyles look. A wonderful masterclass!",
                27 => "Practical, commercial, and beautifully executed haircut education. The instructor breaks down complex layering mechanics into simple, repeatable steps that our whole salon team could follow. The audio quality, close-ups, and explanations were all first-rate. Thank you VC Online for this fantastic training!",
                28 => "The Meo-Ri Collection Part 1 is pure perfection for any stylist who wants to master trending Asian haircuts. The techniques for creating soft movement through internal slide cutting are revolutionary. My clients love the low-maintenance, bouncy feel of their hair after using these methods. Five stars!",
                29 => "Top notch training that delivers immediate salon value and client excitement. The instructor's attention to tension control, comb placement, and scissor sharpness ensures clean, damage-free ends. It has quickly become our go-to reference for layered haircut consultations. Exceptional masterclass throughout!",
                30 => "I am so thrilled with what I learned in this Korean hair trends masterclass! The delicate curtain bangs and sweeping face-framing layers look so elegant and youthful on my clients. The teaching style is clear, encouraging, and rich in practical detail. Thank you VC Online for this amazing educational experience!"
            ]
        ],

        'meo-ri-collection-korean-hair-trends-part-2' => [
            'default_id' => 812,
            'reviews' => [
                1 => "Part 2 takes the Korean hair trends to an even higher professional level. The depth of explanation on texturizing shears and weight removal without losing perimeter shape is brilliant. The styling and finishing tips gave me exact formulas to recreate that effortless Korean salon look. Worth every minute of learning!",
                2 => "Building on Part 1, this advanced module breaks down modern disconnection and interior texture beautifully. Vipul Sir demonstrates how to handle heavier hair sections and create soft, airy volume that lasts all day. My clients are constantly asking for these modern bouncy finishes. Outstanding masterclass!",
                3 => "The advanced layering and weight distribution techniques in Part 2 are a masterclass in modern haircutting. The instructor explains how to cut internal pockets of movement that allow hair to float effortlessly without looking thinned out. The styling section with the blowdryer and round brush was the cherry on top. Five stars!",
                4 => "An incredible continuation of the Meo-Ri series that every creative hairstylist should complete. The lessons on refining the crown area and connecting disconnected fringe sections into side layers were masterfully demonstrated. It has elevated our salon's creative haircut services significantly. Highly recommended!",
                5 => "I loved Part 2 even more than Part 1! The focus on finishing, dry cutting refinement, and personalized texturizing gave me complete control over the final silhouette. The tips on maintaining healthy ends while removing bulk have made a noticeable difference in my client work. Truly fantastic education!",
                6 => "Exceptional haircutting training that combines architectural discipline with fluid international trends. The instructor's breakdown of point cutting depth and shear angle control when working dry was pure gold. It has given me confidence to tackle high-density hair without fear of over-thinning. Top quality masterclass!",
                7 => "The texturizing techniques and finishing methods taught in Part 2 are pure salon luxury. I loved learning how to create that subtle 'C-curl' and 'S-curl' movement using modern cutting and round brush techniques. My clients are raving about how light and bouncy their haircuts feel. Thank you VC Online!",
                8 => "This course delivers high-end international salon education straight to your screen. The instructor explains the nuances of dry cutting and cross-checking layered haircuts to perfection. Our salon team has adopted these exact texturizing protocols with phenomenal client feedback. An indispensable masterclass!",
                9 => "Every chapter of Part 2 provided fresh insights into creating weightless movement and modern shapes. The demonstration on cutting soft layers around the occipital bone to create natural crown lift was brilliant. The video clarity and instructional pacing were flawless throughout. Highly recommended to all stylists!",
                10 => "A masterclass in hair sculpting and weight manipulation that separates ordinary haircuts from luxury salon styling. The instructor demonstrates how subtle angle shifts when point cutting create vastly different texture effects. It has noticeably elevated the quality and consistency of my everyday work. Five stars!",
                11 => "I was blown away by the clarity and precision in this advanced Korean haircut course. The instructor shows how to preserve perimeter fullness while carving out soft, sweeping interior layers that dance with movement. My clients have noticed the change and love the effortless styling at home. Brilliant course!",
                12 => "Very thorough and practical haircutting education that solves real-world salon challenges. Learning how to connect disconnected fringe pieces into soft cheek-grazing layers gave me a reliable framework for busy days. The explanations are concise, professional, and backed by deep industry experience. Highly recommended!",
                13 => "The styling and finishing techniques in Part 2 are worth the price of admission alone. The instructor teaches you how to use heat tools and setting clips to lock in that signature soft Korean wave finish without product heaviness. My salon clients are obsessed with their finished look. Thank you VC Online!",
                14 => "Precision, elegance, and high commercial value define this advanced Korean haircutting masterclass. The lessons on cross-checking weight lines in dry hair and adjusting for natural growth cowlicks were invaluable. It has given me complete confidence when working with discerning, trend-conscious clients. Outstanding training!",
                15 => "This course completed my understanding of trending Asian hairstyles and modern salon finishing. The instructor breaks down complex texturizing concepts into simple, repeatable habits that protect hair integrity. The resulting movement and texture look straight out of a high-fashion editorial. Loved every minute of it!",
                16 => "The camera angles and close-up demonstrations in Part 2 make learning advanced scissor techniques so straightforward. I loved the emphasis on reading the hair's natural fall before making texturizing cuts. My clients are thrilled with how light, bouncy, and easy to maintain their hair feels. A big thumbs up to VC Online!",
                17 => "Clear, structured, and packed with pro-level advice on modern Korean haircutting. The instructor explains the difference between texturizing for volume versus texturizing for collapse with remarkable clarity. It has made my cutting consultations and execution far more deliberate and artistic. Excellent masterclass!",
                18 => "Part 2 of the Meo-Ri series is an absolute masterpiece for any passionate hairstylist! The guidance on styling face-framing sections to accentuate cheekbones gave stunning, photogenic results on my models. The production quality and instruction are unmatched in online hair education. Five stars all the way!",
                19 => "Exceptional continuation that deepens your mastery over modern layered hairstyles. The instructor demonstrates how to manipulate hair volume through elevation control and internal slide cutting with supreme ease. Our salon staff has thoroughly enjoyed implementing these techniques. Thank you for this high quality course!",
                20 => "I gained so much confidence in handling thick, heavy hair textures after watching Part 2. The techniques for internal weight reduction without creating short spiky hairs inside the haircut were a total breakthrough for me. My clients love the soft movement and natural fall. Highly recommend this course to everyone!",
                21 => "Superb educational content that bridges creative design theory with fast-paced salon practicality. The instructor shows how to check the haircut balance from multiple angles and refine dry perimeter lines cleanly. It has elevated the overall standard of our salon's haircut offerings significantly. Great job!",
                22 => "The Meo-Ri Part 2 course is pure inspiration for any hairstylist who loves modern aesthetics. The combination of precision sectioning, internal texturizing, and polished round brush styling delivers breathtaking results. My clients cannot stop touching their hair after these cuts. Thank you VC Online for this fantastic training!",
                23 => "A definitive masterclass on modern texturizing and finishing for Korean hair trends. The instructor's explanations on shear angle, point cutting depth, and elevation dynamics are articulate and easy to master. It has given me the skills to deliver high-end international hairstyles consistently. Top tier education!",
                24 => "I learned so many practical nuances about dry texturizing and styling in this course. The advice on using minimal tension when cutting around the ears and jawline prevented unwanted gaps in the perimeter. It has saved me from common beginner mistakes and elevated my haircuts noticeably. Loved it!",
                25 => "Methodical, inspiring, and rich in technical details that elevate salon artistry. The video demonstrations make it easy to understand how internal weight removal creates external movement and lift. It has brought fresh excitement to my daily haircutting appointments. Highly recommended for all professionals!",
                26 => "Part 2 provided the exact finishing touches I needed to master trending Korean hairstyles. The instructor shows how to blowdry and set the layers to create that effortless, voluminous sweep that clients crave. My salon rebookings for layered cuts have grown substantially. A wonderful masterclass!",
                27 => "Solid professional haircutting training that delivers immediate practical results. The instructor breaks down complex texturizing shears usage, showing how to avoid over-thinning while achieving maximum softness. The video streaming and audio quality are exceptional throughout. Proud to have completed this training!",
                28 => "Such an inspiring and detailed masterclass on contemporary hair design and finishing. The tips on maintaining perimeter weight while creating weightless interior layers gave my clients the dream hair they always wanted. I feel equipped with world-class techniques after taking this course. Five stars!",
                29 => "Top notch continuation of the Meo-Ri collection with immense commercial value for salons. The instructor demonstrates how to customize layered textures according to individual client face shapes and lifestyles. It has significantly elevated the skill level and confidence of our salon team. Outstanding masterclass!",
                30 => "I cannot recommend Part 2 of this Korean haircutting course enough! The advanced texturizing and blowdry styling tips brought my haircutting work to a whole new level of elegance. My clients love the airy bounce and touchable texture of their hair. Thank you VC Online for this incredible educational journey!"
            ]
        ],

        'makeup-mastery-series-by-pooja-chudasama' => [
            'default_id' => 832,
            'reviews' => [
                1 => "Pooja Ma’am is an incredible educator with immense attention to detail. Her guidance on skin prep, color correction, and seamless foundation matching gave me total confidence for bridal looks. The smokey eye module and lip contouring hacks are pure gold. Highly recommend this mastery series to anyone serious about professional makeup artistry.",
                2 => "This makeup mastery course covers everything from base prep to high-glam editorial finishing. The way Pooja Ma'am explains color theory and neutralising stubborn undertones made foundation selection effortless for my clients. The step-by-step smokey eye tutorial is one of the best I have ever watched. A truly comprehensive masterclass!",
                3 => "Every module in this mastery series is packed with professional industry secrets. Learning how to layer cream and powder products without creating texture or caking on mature skin was a game changer for me. The lip contouring and ombré techniques gave my bridal models such a plump, luxurious finish. Worth every single rupee!",
                4 => "An extraordinary masterclass that bridges the gap between basic salon makeup and high-definition luxury artistry. The camera angles capture the exact brush pressure, blending movements, and product consistency needed for flawless results. It has elevated our studio's bridal makeup packages tremendously. Outstanding training by Pooja Ma'am!",
                5 => "I was blown away by the depth and clarity of Pooja Ma'am's teaching. The section on smokey eyes taught me how to blend dark eyeshadows seamlessly without leaving muddy lines or fallout under the eyes. My clients have noticed the difference and bridal bookings have increased significantly. Truly a five-star masterclass!",
                6 => "Outstanding masterclass on foundation chemistry, skin undertones, and long-lasting bridal makeup. The instructor explains why foundations oxidize and how proper skincare prep prevents base breakdown during long wedding functions. It has given me total creative confidence when working under studio lighting. Top tier education!",
                7 => "The Makeup Mastery Series is a complete treasure trove for aspiring and working makeup artists. The step-by-step breakdown of color correcting dark circles and hyperpigmentation without heavy layers of concealer was pure magic. My bridal clients are loving how weightless yet flawless their makeup feels. Thank you VC Online!",
                8 => "Pooja Ma'am's teaching style is clear, inspiring, and deeply rooted in practical industry experience. The module on precision lipstick application and over-lining for symmetry solved many common client challenges I faced. Our entire academy team has learned so much from these comprehensive video modules. Exceptional course!",
                9 => "I learned more in this online course than in several multi-day offline workshops I attended previously. The lessons on building seamless transition shades in smokey eyes and setting without a powdery cast were invaluable. The video resolution and audio clarity made following along effortless. Highly recommended to everyone!",
                10 => "A world-class makeup tutorial series that delivers genuine commercial value for beauty salons. The instructor demonstrates realistic working timelines and product versatility that saves money on makeup kit essentials. It has helped our salon artists deliver stunning party and bridal looks with complete consistency. Brilliant training!",
                11 => "Flawless base application was always my biggest challenge, and this course solved it completely. Pooja Ma'am breaks down skin types, primers, and buffing brush techniques with extraordinary patience and precision. My bridal looks now last through heat and humidity without separating. Thank you VC Online for this fantastic masterclass!",
                12 => "Very detailed, structured, and easy to translate into daily professional makeup bookings. The instructor explains the science behind color wheels and choosing complementary eye makeup for different eye colors. It has refined my blending speed and precision dramatically. An indispensable masterclass for all makeup artists!",
                13 => "The eye makeup modules in this series are simply breathtaking! Learning how to achieve intense smokey depth while keeping the edges soft and diffused was worth the entire course fee. The lipstick durability hacks also ensured my brides look fresh throughout their wedding ceremonies. Five stars all the way!",
                14 => "Precision, artistry, and high professional standards shine through every lesson in this mastery series. The instructor demonstrates clean brush sanitation, product hygiene, and palette mixing that every professional must practice. It has given me total confidence when taking on luxury bridal assignments. Outstanding course!",
                15 => "This course exceeded all my expectations in terms of technique, product knowledge, and video quality. Pooja Ma'am demonstrates how to contour different nose and face shapes naturally without harsh brown streaks. The final bridal look was so radiant, balanced, and timeless. Highly recommend this series to all makeup enthusiasts!",
                16 => "The level of detail in Pooja Ma'am's demonstrations is second to none. I loved how she showed real-time fixes for accidental eyeshadow fallout and uneven lip lines. It taught me how to stay calm and deliver perfection during stressful bridal prep hours. A huge thank you to VC Online for providing such quality education!",
                17 => "Clear, methodical, and packed with practical insights that elevate everyday salon makeup into high fashion. The instructor explains the mechanics of lighting and how flash photography affects makeup undertones. It has made my portfolio shoots look ten times more professional. Excellent masterclass!",
                18 => "I thoroughly enjoyed this course and took pages of detailed notes! The tips on using cream blushes under translucent powder to create a lit-from-within glow gave stunning results on my clients. The instruction is warm, articulate, and rich in practical wisdom. An absolute five-star learning experience!",
                19 => "Exceptional guidance on color theory, product blending, and longevity for bridal makeup. The instructor shows how to customize foundation formulas for oily, dry, and combination skin types without buying dozens of different bottles. Our salon makeup artists have benefited immensely from these lessons. Brilliant work!",
                20 => "Before taking this course, my smokey eyes often turned muddy or too harsh for client tastes. Pooja Ma'am's layering technique and pencil buffer method made creating soft, seductive smokey eyes so foolproof. It has boosted my client bookings and given me tremendous artistic pride. Thank you so much!",
                21 => "Superb educational content with genuine high-end salon application. The instructor explains how product texture and brush density interact on the skin during application. It has helped me choose the right tools for every step and cut down application time by twenty minutes. Highly recommended to all beauty professionals!",
                22 => "The Makeup Mastery Series is pure perfection for anyone who wants to excel in bridal and event makeup. The combination of skin preparation, precision contouring, and classic nude lip artistry delivered breathtaking results. My brides are thrilled with how glowing and photogenic their makeup looks in their wedding albums. Outstanding!",
                23 => "A definitive masterclass on contemporary makeup artistry and client customization. The instructor's explanations of foundation blending, eyelid priming, and water-resistant setting sprays are articulate and thorough. It has given our studio artists the skills to deliver high-end red carpet looks with ease. Top tier education!",
                24 => "I was amazed at how much practical, real-world knowledge was shared throughout this course. The tips on setting the under-eye area without enhancing fine lines or dry patches made an immediate difference in my work. It saved me years of trial and error with luxury makeup products. Loved every minute of it!",
                25 => "Methodical, inspiring, and rich in technical details that elevate salon makeup into true artistry. The video demonstrations make it easy to understand how light reflection works across different facial contours. It has brought fresh excitement and higher revenue to our bridal makeup services. Highly recommended!",
                26 => "This course gave me the exact formulas I needed to master long-lasting base makeup and glamorous smokey eyes. Pooja Ma'am explains how to choose lipstick undertones that brighten the client's complexion instantly. My clients are constantly raving about how lightweight and comfortable their makeup feels. A wonderful masterclass!",
                27 => "Solid professional makeup training that delivers immediate practical results. The instructor breaks down complex makeup chemistry into simple, practical guidelines that our whole team could implement. The video streaming, close-up camera angles, and sound quality were all first-rate. Thank you VC Online for this fantastic training!",
                28 => "The Makeup Mastery Series by Pooja Chudasama is pure gold for every practicing makeup artist. The techniques for creating seamless gradient smokey eyes and perfectly balanced lip shapes are revolutionary. I feel completely confident handling any skin type or bridal requirement now. Five stars!",
                29 => "Top notch training that delivers immense value for salons, freelancers, and academy students alike. The instructor's attention to detail during color matching, blending, and precision lash application ensures flawless results every time. It has significantly elevated the reputation of our studio's bridal makeup work. Outstanding!",
                30 => "I am so grateful for this incredible makeup mastery course! Pooja Ma'am's techniques for flawless skin prep, soft smokey eye definition, and smudge-proof lipstick gave my clients the bridal look of their dreams. The teaching style is clear, encouraging, and full of professional wisdom. Thank you VC Online for this amazing masterclass!"
            ]
        ],

        'barcode-lites-with-vipul-chudasama' => [
            'default_id' => 2230,
            'reviews' => [
                1 => "Vipul Sir's barcode lighting technique is absolute genius! The methodical sectioning and clean foil work ensure there are no harsh bleed marks or uneven tones. My clients are thrilled with the multi-dimensional contrast and seamless regrowth blend. Exceptional masterclass that elevates salon coloring to the next level.",
                2 => "This masterclass completely transformed the way I approach high-contrast hair coloring. Vipul Sir breaks down the exact sectioning angles and foil placement protocols needed to achieve that striking barcode effect. The resulting blend of highlights and lowlights is commercial, modern, and breathtaking. A must-watch for colorists!",
                3 => "Learning barcode lites from the master himself was an incredible learning experience. The explanation of bleach formulation, developer volume selection, and gentle incubation inside foils was pure gold. My clients love the soft dimensional shine and seamless movement it creates in their hair. Worth every single penny!",
                4 => "An eye-opening course on modern creative color techniques for high-end salons. Vipul Sir explains how to calculate section density so the barcode ribbons do not get lost when the hair is styled straight or in waves. It has given our salon a unique signature color service that clients are booking weeks in advance. Outstanding!",
                5 => "I was genuinely amazed by the precision and systematic discipline taught in this barcode lights masterclass. The tips on maintaining tension when placing foils close to the root without causing bleeding lines solved a major headache for me. The finished result was glossy, modern, and stunning. Five stars without hesitation!",
                6 => "Vipul Sir's barcode lights technique represents the highest standard of international hair coloring. The instructor explains why traditional foiling patterns often create striped results and how barcode sectioning achieves soft multi-tonal harmony. It has elevated our salon's color ticket average significantly. Top tier education!",
                7 => "The Barcode Lites course is a complete game changer for salon colorists. The step-by-step guidance on weaving, slice depth, and toning formulation ensured my very first client trial came out picture-perfect. My clients are obsessed with how low-maintenance and glossy their hair looks. Thank you VC Online!",
                8 => "The foil placement blueprints and section geometry shared by Vipul Sir in this course are world-class. It gave me the technical confidence to tackle dark virgin hair and achieve clean, even lifts without compromising hair health. Every salon owner should make this mandatory for their color team. Brilliant training!",
                9 => "I learned so much about color placement, visual balance, and clean work ethics from this masterclass. The demonstration on applying gloss toners at the bowl to harmonize the barcode ribbons with the natural base was pure artistry. The video production and close-ups made learning effortless. Highly recommended!",
                10 => "A concise yet powerful masterclass that demystifies advanced highlighting and ribbon coloring. Vipul Sir's explanation of developer strength variations across different head zones was an eye-opener. It has helped our salon team achieve completely consistent lift and tone across all hair lengths. Exceptional training!",
                11 => "Mastering barcode lites was on my wishlist for months, and this course delivered everything I hoped for. The instructor demonstrates clean foil folding techniques that prevent slippage and bleeding near the scalp. My salon clients love the modern, dimensional look that catches the light so beautifully. Thank you VC Online!",
                12 => "Very practical, methodical, and packed with salon-ready color knowledge. Vipul Sir explains the chemistry of lightening products and how to protect hair elasticity during multi-foil lightening services. It has elevated my speed, precision, and consultation authority with color clients. An indispensable course for colorists!",
                13 => "The barcode lighting effect creates such an elegant and high-fashion finish on hair of all lengths. I especially loved learning how to blend the perimeter face-framing pieces with the interior barcode sections seamlessly. My clients have received so many compliments and rebooking rates are at an all-time high. Loved this course!",
                14 => "Precision sectioning, clean application, and artistic vision come together masterfully in this course. Vipul Sir shows how to work methodically from the nape to the crown while monitoring processing times accurately. It has given me total confidence when performing full-head creative highlighting services. Outstanding masterclass!",
                15 => "This course answered all my questions about creating high-contrast ribbons without leaving harsh stripes or patchy spots. The instructor breaks down foil spacing and product saturation with extraordinary clarity. The finished blowdry and wave styling showed off the dimension to perfection. Highly recommend this masterclass!",
                16 => "The camera clarity and Vipul Sir's clear narration make following this advanced color masterclass an absolute delight. I loved how he demonstrated corrective steps for bleeding and uneven natural bases during live applications. It has elevated our studio's coloring portfolio and attracted high-ticket clients. Brilliant training!",
                17 => "Clear, structured, and full of actionable techniques that transform everyday foil highlights into bespoke hair color art. The instructor explains how foil angles interact with head curvature to produce natural-looking movement. It has made my color services much more predictable and artistic. Excellent masterclass!",
                18 => "I thoroughly enjoyed this barcode lites course and practiced the sectioning pattern on a mannequin immediately! The foil placement is so logical, clean, and ergonomic to execute behind the chair. The finished result looks expensive, dimensional, and effortlessly chic. An absolute five-star learning experience!",
                19 => "Exceptional guidance on hair lightening, foil discipline, and toning formulations for trending salon colors. Vipul Sir shows how to maintain structural strength in chemically treated hair while achieving vibrant contrast. Our salon stylists have learned so much from this masterclass. Thank you for this top quality course!",
                20 => "Before taking this course, I struggled to achieve clean contrast between lightened pieces and darker base tones without muddy blending. Vipul Sir's barcode placement gave me the exact formulas and angles to create crisp, luminous ribbons. It has boosted my color clientele significantly. Thank you so much!",
                21 => "Superb educational content that bridges creative color concepts with fast-paced salon practicality. The instructor explains how product consistency affects foil adhesion and lift speed across different hair types. It has helped me cut my foiling application time while delivering cleaner results. Highly recommended to all colorists!",
                22 => "The Barcode Lites masterclass is pure inspiration for any colorist who loves modern, high-impact salon work. The combination of clean sectioning, precise saturation, and luminous gloss toning produces breathtaking results. My clients cannot stop raving about how radiant their hair looks under sunlight. Outstanding!",
                23 => "A definitive masterclass on contemporary foil highlights and creative color placement. Vipul Sir's explanations of section thickness, elevation during application, and root feathering are articulate and thorough. It has given our salon team the tools to deliver international-standard color work with ease. Top tier education!",
                24 => "I learned so many practical nuances about hair lightening and foil placement in just a couple of hours. The tips on feathering product near the roots to eliminate hard demarcation lines made an immediate difference in my work. It saved me years of trial and error behind the chair. Loved every minute of it!",
                25 => "Methodical, inspiring, and rich in technical details that elevate salon coloring into high fashion. The video demonstrations make it easy to understand how foil placement directions affect hair fall when styled in different ways. It has brought fresh excitement and higher profits to our color department. Highly recommended!",
                26 => "This course gave me the exact blueprints I needed to master high-contrast hair lightening safely and beautifully. Vipul Sir explains how to formulate gentle toners that neutralize brassiness without darkening the blonde ribbons. My clients are thrilled with the long-lasting shine and dimension. A wonderful masterclass!",
                27 => "Solid professional color training that delivers immediate practical results. The instructor breaks down complex lightening dynamics into simple, repeatable steps that our whole salon team could follow. The video streaming, close-up camera angles, and sound quality were all first-rate. Thank you VC Online for this fantastic training!",
                28 => "The Barcode Lites masterclass by Vipul Chudasama is pure gold for every practicing hair colorist. The techniques for achieving seamless dimensional highlights without harsh bleach lines are revolutionary. I feel completely confident recommending creative color transformations to my clients now. Five stars!",
                29 => "Top notch training that delivers immense value for salons and independent color specialists alike. The instructor's attention to detail during foil placement, feathering, and pH-balancing post-lightening care ensures flawless, healthy hair. It has significantly elevated the reputation of our salon's color services. Outstanding!",
                30 => "I am so thrilled with what I learned in this Barcode Lites masterclass! Vipul Sir's techniques for clean foil placement, zero bleeding, and gorgeous dimensional toning gave my clients the hair transformation of their dreams. The teaching style is clear, encouraging, and full of industry wisdom. Thank you VC Online for this amazing masterclass!"
            ]
        ]
    ];

    foreach ( $course_definitions as $course_slug => $c_data ) {
        $default_id = $c_data['default_id'];
        $reviews    = $c_data['reviews'];

        $course = get_page_by_path( $course_slug, OBJECT, 'courses' );
        $course_id = ( $course && isset( $course->ID ) ) ? $course->ID : $default_id;

        foreach ( $reviews as $student_num => $comment_text ) {
            $user_id     = isset( $resolved_student_ids[ $student_num ] ) ? $resolved_student_ids[ $student_num ] : 0;
            $author_name = isset( $students[ $student_num ]['name'] ) ? $students[ $student_num ]['name'] : '';

            $comment = null;
            if ( $user_id ) {
                $comment = $wpdb->get_row( $wpdb->prepare(
                    "SELECT comment_ID FROM {$wpdb->comments} 
                     WHERE comment_post_ID = %d AND user_id = %d AND comment_type = 'tutor_course_rating' 
                     LIMIT 1",
                    $course_id,
                    $user_id
                ) );
            }

            if ( ! $comment ) {
                // Fallback search by author name variants on this course
                $comment = $wpdb->get_row( $wpdb->prepare(
                    "SELECT comment_ID FROM {$wpdb->comments} 
                     WHERE comment_post_ID = %d 
                       AND comment_type = 'tutor_course_rating' 
                       AND (comment_author = %s OR comment_author = %s OR comment_author = %s OR comment_author = %s)
                     LIMIT 1",
                    $course_id,
                    sprintf( 'demo_student_%02d', $student_num ),
                    sprintf( 'demo_student_%d', $student_num ),
                    sprintf( 'Student %02d', $student_num ),
                    sprintf( 'Student %d', $student_num )
                ) );
            }

            if ( $comment ) {
                $wpdb->update(
                    $wpdb->comments,
                    [
                        'comment_content' => $comment_text,
                        'comment_author'  => $author_name,
                    ],
                    [ 'comment_ID' => $comment->comment_ID ]
                );
            }
        }
    }

    if ( function_exists( 'wp_cache_flush' ) ) {
        wp_cache_flush();
    }

    update_option( 'vca_dummy_reviews_synced_v2', 1 );
}
