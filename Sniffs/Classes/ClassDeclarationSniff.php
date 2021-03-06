<?php
/**
 * Class Declaration Test.
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006-2014 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */

namespace ONGR\Sniffs\Classes;

use PHP_CodeSniffer_File;
use PSR2_Sniffs_Classes_ClassDeclarationSniff;

/**
 * Class Declaration Test.
 *
 * Checks the declaration of the class and its inheritance is correct.
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006-2014 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 * @version   Release: @package_version@
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */
class ClassDeclarationSniff extends PSR2_Sniffs_Classes_ClassDeclarationSniff
{
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
     * @param int                  $stackPtr  The position of the current token
     *                                        in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        // We want all the errors from the PSR2 standard, plus some of our own.
        parent::process($phpcsFile, $stackPtr);

        // Check that this is the only class or interface in the file.
        $nextClass = $phpcsFile->findNext([T_CLASS, T_INTERFACE], ($stackPtr + 1));
        if ($nextClass !== false) {
            // We have another, so an error is thrown.
            $error = 'Only one interface or class is allowed in a file';
            $phpcsFile->addError($error, $nextClass, 'MultipleClasses');
        }
    }

    /**
     * Processes the opening section of a class declaration.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
     * @param int                  $stackPtr  The position of the current token
     *                                        in the stack passed in $tokens.
     *
     * @return void
     */
    public function processOpen(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        parent::processOpen($phpcsFile, $stackPtr);

        $tokens = $phpcsFile->getTokens();

        if ($tokens[($stackPtr - 1)]['code'] === T_WHITESPACE) {
            $prevContent = $tokens[($stackPtr - 1)]['content'];
            if ($prevContent !== $phpcsFile->eolChar) {
                $blankSpace = substr($prevContent, strpos($prevContent, $phpcsFile->eolChar));
                $spaces = strlen($blankSpace);

                if (in_array($tokens[($stackPtr - 2)]['code'], [T_ABSTRACT, T_FINAL]) === false) {
                    if ($spaces !== 0) {
                        $type = strtolower($tokens[$stackPtr]['content']);
                        $error = 'Expected 0 spaces before %s keyword; %s found';
                        $data = [
                            $type,
                            $spaces,
                        ];
                        $phpcsFile->addError($error, $stackPtr, 'SpaceBeforeKeyword', $data);
                    }
                }
            }
        }

        $curlyBrace = $tokens[$stackPtr]['scope_opener'];
        $i = 1;
        while ($tokens[($curlyBrace + $i)]['code'] === T_WHITESPACE && $i < count($tokens)) {
            $i++;
        }
        $blankLineCount = ($tokens[($curlyBrace + $i)]['line'] - $tokens[$curlyBrace]['line']) - 1;
        if ($blankLineCount > 0) {
            $data = [$blankLineCount];
            $error = 'Expected no blank lines after an opening brace, %s found';
            $phpcsFile->addError($error, $curlyBrace, 'OpenBraceBlankLines', $data);
        }
    }

    /**
     * Processes the closing section of a class declaration.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
     * @param int                  $stackPtr  The position of the current token
     *                                        in the stack passed in $tokens.
     *
     * @return void
     */
    public function processClose(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        $closeBrace = $tokens[$stackPtr]['scope_closer'];
        if ($tokens[($closeBrace - 1)]['code'] === T_WHITESPACE) {
            $prevContent = $tokens[($closeBrace - 1)]['content'];
            if ($prevContent !== $phpcsFile->eolChar) {
                $blankSpace = substr($prevContent, strpos($prevContent, $phpcsFile->eolChar));
                $spaces = strlen($blankSpace);
                if ($spaces !== 0) {
                    if ($tokens[($closeBrace - 1)]['line'] !== $tokens[$closeBrace]['line']) {
                        $error = 'Expected 0 spaces before closing brace; newline found';
                        $phpcsFile->addError($error, $closeBrace, 'NewLineBeforeCloseBrace');
                    } else {
                        $error = 'Expected 0 spaces before closing brace; %s found';
                        $data = [$spaces];
                        $phpcsFile->addError($error, $closeBrace, 'SpaceBeforeCloseBrace', $data);
                    }
                }
            }
        }

        // Check that the closing brace has one blank line after it.
        $nextContent = $phpcsFile->findNext([T_WHITESPACE, T_COMMENT], ($closeBrace + 1), null, true);
        if ($nextContent !== false) {
            $nextLine = $tokens[$nextContent]['line'];
            $braceLine = $tokens[$closeBrace]['line'];
            if ($braceLine === $nextLine) {
                $error = 'Closing brace of a %s must be followed by a single blank line';
                $data = [$tokens[$stackPtr]['content']];
                $phpcsFile->addError($error, $closeBrace, 'NoNewlineAfterCloseBrace', $data);
            } elseif ($nextLine !== ($braceLine + 2)) {
                $difference = ($nextLine - $braceLine - 1);
                $error = 'Closing brace of a %s must be followed by a single blank line; found %s';
                $data = [
                    $tokens[$stackPtr]['content'],
                    $difference,
                ];
                $phpcsFile->addError($error, $closeBrace, 'NewlinesAfterCloseBrace', $data);
            }
        }

        // Check the closing brace is on it's own line, but allow
        // for comments like "//end class".
        $nextContent = $phpcsFile->findNext(T_COMMENT, ($closeBrace + 1), null, true);
        if ($tokens[$nextContent]['content'] !== $phpcsFile->eolChar
            && $tokens[$nextContent]['line'] === $tokens[$closeBrace]['line']
        ) {
            $error = 'Closing %s brace must be on a line by itself';
            $data = [$tokens[$stackPtr]['content']];
            $phpcsFile->addError($error, $closeBrace, 'CloseBraceSameLine', $data);
        }
    }
}
