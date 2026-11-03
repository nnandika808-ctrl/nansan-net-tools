<?php

namespace Database\Seeders;

use App\Models\CmsPage;
use App\Models\DocumentationArticle;
use App\Models\Tool;
use Illuminate\Database\Seeder;

class EnglishContentSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'verification' => ['Generator verification', 'Understand publication states, output comparisons and RouterOS testing.', "## Accuracy before publication\n\nEach generator requires a form audit, input validation, output comparison and testing in an authorized RouterOS lab.\n\n- **BLOCKED**: required data or verification is missing.\n- **OUTPUT MATCHED**: the compared cases match; this is not a claim about all combinations.\n- **ROUTEROS TESTED**: relevant lab checks are recorded.\n- **COMPLETE**: all publication criteria are met.\n\nExact comparison covers the entire permitted text. Semantic comparison covers configuration effects; these results are reported separately.\n\nGenerators that have not met publication requirements stay locked. No sample is presented as ready for deployment."],
            'account-recovery' => ['Accounts and recovery codes', 'Use a username, save recovery codes and manage device sessions.', "## No required email\n\nChoose a unique username and a password with at least 12 characters, letters and numbers.\n\n## Keep your recovery codes\n\nRegistration displays eight recovery codes once. Store them safely. Each code can be used only once. Never share passwords or recovery codes.\n\nThe Account page lets you change your password, revoke device sessions and issue new recovery codes. New codes invalidate the previous set.\n\n## Account deletion\n\nDeletion requires your password. The account is disabled immediately and scheduled for deletion after 30 days. You can sign in to cancel during that grace period."],
            'safe-workflow' => ['A safe configuration workflow', 'Review inputs, document changes and verify in your lab.', "## Before using output\n\n1. Record your RouterOS version and topology.\n2. Check interfaces, subnets, gateways and existing rules.\n3. Back up the router configuration.\n4. Review every script step manually.\n5. Test in a suitable lab before changes to an operational network.\n\nNANSAN NET Tools does not connect to or configure your router. Save only the parameters you need. Do not put passwords or tokens in names, comments, presets or feedback."],
        ] as $slug => [$title,$excerpt,$body]) {
            DocumentationArticle::where('slug', $slug)->whereNull('title_en')->update(['title_en' => $title, 'excerpt_en' => $excerpt, 'body_en' => $body]);
        }
        foreach ([
            'about' => ['About NANSAN NET Tools', "## Built for the networking community\n\nNANSAN NET Tools is a MikroTik workspace owned by NANSAN NET. Every feature is free, with no subscriptions or paid plans.\n\nThe five priority generators are being developed with testing evidence first. The application identity and code are independently developed."],
            'contact' => ['Contact', "## Contact NANSAN NET\n\nSign in and use **Feedback & support** to report bugs or suggest improvements.\n\nAdditional public contact details await the project owner's confirmation. Never send passwords, tokens or sensitive configurations."],
            'privacy-policy' => ['Privacy Policy', "## Draft privacy policy\n\nThe application stores your username, password hash, preferences and content you choose to save. Sessions retain device information and IP addresses for access management. Email is not required.\n\nRecovery codes are hashed. Turnstile verifies registration where enabled.\n\nOperational audits are retained for 90 days and user activity for 30 days. Verified account deletion has a 30-day grace period. Backup handling and retention grounds require owner approval before launch.\n\nProcessing purposes, controller identity, contact details, third-party processors and user-rights procedures require project-owner review before production."],
            'terms' => ['Terms & Conditions', "## Draft terms of use\n\nEvery feature is free. Users must protect their account credentials, understand their network configuration and review output before using it. Do not use the platform for unauthorized actions.\n\nGenerator availability depends on verification and testing. Unpublished output is unavailable for use.\n\nLiability, jurisdiction, dispute handling and service changes require project-owner review."],
            'cookies' => ['Cookie Policy', "## Draft cookie policy\n\nSession cookies and CSRF protection support authentication and application security. Remember me uses a persistent authentication cookie. Language and theme preferences may be stored in device local storage.\n\nDeployment-specific Cloudflare and Turnstile cookies require owner review. Verify the final cookie inventory on staging."],
            'disclaimer' => ['MikroTik Script Disclaimer', "## Review and test configurations\n\nGenerator output assists with preparing configurations. Do not apply it without understanding each command's effects on your router, network and existing rules.\n\nBack up your configuration and use a lab environment. Independently review suitability for your topology. NANSAN NET Tools does not automatically run scripts or access users' routers.\n\nThis statement is a draft for project-owner review before production."],
        ] as $slug => [$title,$body]) {
            CmsPage::where('slug', $slug)->whereNull('title_en')->update(['title_en' => $title, 'body_en' => $body]);
        }
        foreach (['G01' => 'Plan application traffic routes through a selected gateway.', 'G02' => 'Manage traffic priority with Simple Queue or Queue Tree.', 'G03' => 'Distribute connections across WAN links with bandwidth-ratio options.', 'G04' => 'Plan Starlink load balancing with dynamic DHCP gateways.', 'G05' => 'Select game traffic routes using verified rules.', 'G06' => 'Hierarchical Simple Queue or Queue Tree bandwidth management with RAW and Mangle classification.', 'G07' => 'Calculate MikroTik burst limits, thresholds, and burst times for queues and user profiles.', 'G07' => 'Calculate MikroTik burst limits, thresholds, and burst times for queues and user profiles.'] as $id => $description) {
            Tool::where('generator_id', $id)->whereNull('description_en')->update(['description_en' => $description]);
        }
    }
}
