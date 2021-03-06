<?php
/**
 * Parses and verifies the variable doc comment.
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

namespace ONGR\Sniffs\Commenting;

use PHP_CodeSniffer;
use PHP_CodeSniffer_CommentParser_CommentElement;
use PHP_CodeSniffer_CommentParser_MemberCommentParser;
use PHP_CodeSniffer_CommentParser_ParserException;
use PHP_CodeSniffer_CommentParser_SingleElement;
use PHP_CodeSniffer_File;
use PHP_CodeSniffer_Standards_AbstractVariableSniff;

/**
 * Parses and verifies the variable doc comment.
 *
 * Verifies that :
 * <ul>
 *  <li>A variable doc comment exists.</li>
 *  <li>Short description ends with a full stop.</li>
 *  <li>There is a blank line after the short description.</li>
 *  <li>There is a blank line between the description and the tags.</li>
 *  <li>Check the order, indentation and content of each tag.</li>
 * </ul>
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
class VariableCommentSniff extends PHP_CodeSniffer_Standards_AbstractVariableSniff
{
    /**
     * @var PHP_CodeSniffer_CommentParser_MemberCommentParser The header comment parser for the current file.
     */
    protected $commentParser = null;

    /**
     * Called to process class member vars.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
     * @param int                  $stackPtr  The position of the current token
     *                                        in the stack passed in $tokens.
     *
     * @return void
     */
    public function processMemberVar(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $this->currentFile = $phpcsFile;
        $tokens = $phpcsFile->getTokens();
        $commentToken = [
            T_COMMENT,
            T_DOC_COMMENT,
        ];

        // Extract the var comment docblock.
        $commentEnd = $phpcsFile->findPrevious($commentToken, ($stackPtr - 3));
        if ($commentEnd !== false && $tokens[$commentEnd]['code'] === T_COMMENT) {
            $phpcsFile->addError('You must use "/**" style comments for a variable comment', $stackPtr, 'WrongStyle');

            return;
        } elseif ($commentEnd === false || $tokens[$commentEnd]['code'] !== T_DOC_COMMENT) {
            $phpcsFile->addError('Missing variable doc comment', $stackPtr, 'Missing');

            return;
        } else {
            // Make sure the comment we have found belongs to us.
            $commentFor = $phpcsFile->findNext([T_VARIABLE, T_CLASS, T_INTERFACE], ($commentEnd + 1));
            if ($commentFor !== $stackPtr) {
                $phpcsFile->addError('Missing variable doc comment', $stackPtr, 'Missing');

                return;
            }
        }

        $commentStart = ($phpcsFile->findPrevious(T_DOC_COMMENT, ($commentEnd - 1), null, true) + 1);
        $commentString = $phpcsFile->getTokensAsString($commentStart, ($commentEnd - $commentStart + 1));

        // Parse the header comment docblock.
        try {
            $this->commentParser = new PHP_CodeSniffer_CommentParser_MemberCommentParser($commentString, $phpcsFile);
            $this->commentParser->parse();
        } catch (PHP_CodeSniffer_CommentParser_ParserException $e) {
            $line = ($e->getLineWithinComment() + $commentStart);
            $phpcsFile->addError($e->getMessage(), $line, 'ErrorParsing');

            return;
        }

        /** @var PHP_CodeSniffer_CommentParser_CommentElement $comment */
        $comment = $this->commentParser->getComment();
        if ($comment === null) {
            $error = 'Variable doc comment is empty';
            $phpcsFile->addError($error, $commentStart, 'Empty');

            return;
        }

        // The first line of the comment should just be the /** code.
        $eolPos = strpos($commentString, $phpcsFile->eolChar);
        $firstLine = substr($commentString, 0, $eolPos);
        if ($firstLine !== '/**') {
            $error = 'The open comment tag must be the only content on the line';
            $phpcsFile->addError($error, $commentStart, 'ContentAfterOpen');
        }

        // Check for a comment description.
        $short = $comment->getShortComment();
        $long = '';
        if (trim($short) === '') {
            $newlineCount = 1;
        } else {
            // No extra newline before short description.
            $newlineSpan = strspn($short, $phpcsFile->eolChar);
            if ($short !== '' && $newlineSpan > 0) {
                $error = 'Extra newline(s) found before variable comment short description';
                $phpcsFile->addError($error, ($commentStart + 1), 'SpacingBeforeShort');
            }

            $newlineCount = (substr_count($short, $phpcsFile->eolChar) + 1);

            // Exactly one blank line between short and long description.
            $long = $comment->getLongComment();
            if (empty($long) === false) {
                $between = $comment->getWhiteSpaceBetween();
                $newlineBetween = substr_count($between, $phpcsFile->eolChar);
                if ($newlineBetween !== 2) {
                    $error = 'There must be exactly one blank line between descriptions in variable comment';
                    $phpcsFile->addError($error, ($commentStart + $newlineCount + 1), 'SpacingBetween');
                }

                $newlineCount += $newlineBetween;

                $testLong = trim($long);
                if (preg_match('|\p{Lu}|u', $testLong[0]) === 0) {
                    $error = 'Variable comment long description must start with a capital letter';
                    $phpcsFile->addError($error, ($commentStart + $newlineCount), 'LongNotCapital');
                }
            } else {
                // There is no long comment.
                $error = 'Short description should be after variable type '
                    . 'or there should be both long and short description';
                $phpcsFile->addError($error, ($commentStart + $newlineCount), 'BadMultilineVariable');
            }

            // Short description must be single line and end with a full stop.
            $testShort = trim($short);
            $lastChar = $testShort[(strlen($testShort) - 1)];
            if (substr_count($testShort, $phpcsFile->eolChar) !== 0) {
                $error = 'Variable comment short description must be on a single line';
                $phpcsFile->addError($error, ($commentStart + 1), 'ShortSingleLine');
            }

            if (preg_match('|\p{Lu}|u', $testShort[0]) === 0) {
                $error = 'Variable comment short description must start with a capital letter';
                $phpcsFile->addError($error, ($commentStart + 1), 'ShortNotCapital');
            }

            if ($lastChar !== '.') {
                $error = 'Variable comment short description must end with a full stop';
                $phpcsFile->addError($error, ($commentStart + 1), 'ShortFullStop');
            }
        }

        // Exactly one blank line before tags.
        $tags = $this->commentParser->getTagOrders();
        if (count($tags) > 1) {
            $newlineSpan = $comment->getNewlineAfter();
            if (trim($short) !== '' && $newlineSpan !== 2) {
                $error = 'There must be exactly one blank line before the tags in variable comment';
                if ($long !== '') {
                    $newlineCount += (substr_count($long, $phpcsFile->eolChar) - $newlineSpan + 1);
                }

                $phpcsFile->addError($error, ($commentStart + $newlineCount), 'SpacingBeforeTags');
            }
        }

        // Check each tag.
        $this->processVar($commentStart, $commentEnd);
        $this->processSees($commentStart);

        // The last content should be a newline and the content before
        // that should not be blank. If there is more blank space
        // then they have additional blank lines at the end of the comment.
        $words = $this->commentParser->getWords();
        $lastPos = (count($words) - 1);
        if (trim($words[($lastPos - 1)]) !== ''
            || strpos($words[($lastPos - 1)], $this->currentFile->eolChar) === false
            || trim($words[($lastPos - 2)]) === ''
        ) {
            $error = 'Additional blank lines found at end of variable comment';
            $this->currentFile->addError($error, $commentEnd, 'SpacingAfter');
        }
    }

    /**
     * Process the var tag.
     *
     * @param int $commentStart The position in the stack where the comment started.
     * @param int $commentEnd   The position in the stack where the comment ended.
     *
     * @return void
     */
    protected function processVar($commentStart, $commentEnd)
    {
        /** @var PHP_CodeSniffer_CommentParser_SingleElement $var */
        $var = $this->commentParser->getVar();

        if ($var !== null) {
            $errorPos = ($commentStart + $var->getLine());
            $index = array_keys($this->commentParser->getTagOrders(), 'var');

            if (count($index) > 1) {
                $error = 'Only 1 @var tag is allowed in variable comment';
                $this->currentFile->addError($error, $errorPos, 'DuplicateVar');

                return;
            }

            if ($index[0] !== 1) {
                $error = 'The @var tag must be the first tag in a variable comment';
                $this->currentFile->addError($error, $errorPos, 'VarOrder');
            }

            $content = $var->getContent();
            if (empty($content) === true) {
                $error = 'Var type missing for @var tag in variable comment';
                $this->currentFile->addError($error, $errorPos, 'MissingVarType');

                return;
            } else {
                $suggestedType = PHP_CodeSniffer::suggestType($content);
                if ($suggestedType === 'boolean') {
                    $suggestedType = 'bool';
                } elseif ($suggestedType === 'integer') {
                    $suggestedType = 'int';
                }
                if ($content !== $suggestedType && strpos($content, $suggestedType . ' ') === false) {
                    $error = 'Expected "%s"; found "%s" for @var tag in variable comment';
                    $data = [
                        $suggestedType,
                        $content,
                    ];
                    $this->currentFile->addError($error, $errorPos, 'IncorrectVarType', $data);
                }
            }

            $spacing = substr_count($var->getWhitespaceBeforeContent(), ' ');
            if ($spacing !== 1) {
                $error = '@var tag indented incorrectly; expected 1 space but found %s';
                $data = [$spacing];
                $this->currentFile->addError($error, $errorPos, 'VarIndent', $data);
            }

            $this->processVarComment($var, $errorPos);
        } else {
            $error = 'Missing @var tag in variable comment';
            $this->currentFile->addError($error, $commentEnd, 'MissingVar');
        }
    }

    /**
     * Process the see tags.
     *
     * @param int $commentStart The position in the stack where the comment started.
     *
     * @return void
     */
    protected function processSees($commentStart)
    {
        /** @var PHP_CodeSniffer_CommentParser_SingleElement[] $sees */
        $sees = $this->commentParser->getSees();
        if (empty($sees) === false) {
            foreach ($sees as $see) {
                $errorPos = ($commentStart + $see->getLine());
                $content = $see->getContent();
                if (empty($content) === true) {
                    $error = 'Content missing for @see tag in variable comment';
                    $this->currentFile->addError($error, $errorPos, 'EmptySees');
                    continue;
                }

                $spacing = substr_count($see->getWhitespaceBeforeContent(), ' ');
                if ($spacing !== 1) {
                    $error = '@see tag indented incorrectly; expected 1 spaces but found %s';
                    $data = [$spacing];
                    $this->currentFile->addError($error, $errorPos, 'SeesIndent', $data);
                }
            }
        }
    }

    /**
     * Called to process a normal variable.
     *
     * Not required for this sniff.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The PHP_CodeSniffer file where this token was found.
     * @param int                  $stackPtr  The position where the double quoted
     *                                        string was found.
     *
     * @return void
     */
    protected function processVariable(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
    }

    /**
     * Called to process variables found in double quoted strings.
     *
     * Not required for this sniff.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The PHP_CodeSniffer file where this token was found.
     * @param int                  $stackPtr  The position where the double quoted
     *                                        string was found.
     *
     * @return void
     */
    protected function processVariableInString(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
    }

    /**
     * Check if var comment is correct.
     *
     * @param PHP_CodeSniffer_CommentParser_SingleElement $var
     * @param int                                         $errorPos
     */
    protected function processVarComment(PHP_CodeSniffer_CommentParser_SingleElement $var, $errorPos)
    {
        $comment = $var->getContent();

        // Pattern from PHP_CodeSniffer::suggestType.
        $comment = trim(preg_replace('/^array\(\s*([^\s^=^>]*)(\s*=>\s*(.*))?\s*\)/i', '', $comment, 1, $count));

        if (!$count) {
            $space = strpos($comment, ' ');
            if ($space === false) {
                return;
            }
            $comment = substr($comment, $space + 1);
        }

        if ($comment === '') {
            return;
        }

        if (substr($comment, 0, 1) == '$') {
            $this->currentFile->addError(
                'Class field docs should not contain field name',
                $errorPos
            );

            return;
        }

        if (!in_array(substr($comment, -1, 1), ['.', '?', '!'])) {
            $this->currentFile->addError(
                'Variable comments must end in full-stops, exclamation marks, or question marks',
                $errorPos,
                'VariableComment'
            );
        }

        $firstLetter = substr($comment, 0, 1);
        if (strtoupper($firstLetter) !== $firstLetter) {
            $this->currentFile->addError(
                'Variable comments must must start with a capital letter',
                $errorPos,
                'VariableComment'
            );
        }
    }
}
