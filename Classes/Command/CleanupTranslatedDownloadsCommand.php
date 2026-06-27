<?php

declare(strict_types=1);

namespace Ppl\PplDeeplV3Translate\Command;

use Ppl\PplDeeplV3Translate\Service\TranslatedDownloadStorage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Removes expired private translated-document downloads
 * (var/transient/ppl_deepl_v3_translate/downloads). Intended to be run from the
 * TYPO3 Scheduler or cron; uploads also clean up opportunistically.
 */
#[AsCommand(
    name: 'ppl:deepl-v3:cleanup-downloads',
    description: 'Delete expired private translated-document downloads (DeepL v3).'
)]
final class CleanupTranslatedDownloadsCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $removed = GeneralUtility::makeInstance(TranslatedDownloadStorage::class)->purgeExpired();
        $output->writeln(sprintf('Removed %d expired translated download(s).', $removed));

        return Command::SUCCESS;
    }
}
