<?php

namespace KhalsaJio\AI\Nexus\Util;

/**
 * Safety utility for managing prompts and filtering responses
 */
class SafetyManager
{
    /**
     * Default system message to help guide the LLM toward safe outputs
     *
     * @var string
     */
    private static string $defaultSafetyInstruction = '
        Always adhere to ethical guidelines and avoid generating content that would be harmful,
        illegal, deceptive, or unethical. Respond with appropriate, factual information
        that is helpful to the user without enabling harmful activities.
        If a request seems harmful, provide a safer alternative or respectfully decline.
    ';

    /**
     * Flag patterns that might indicate problematic content
     *
     * @var array
     */
    private static array $contentWarningPatterns = [
        'harmful_content' => '/\b(how to (hack|steal|attack)|instructions for (creating|building) (weapons|bombs|poisons))/i',
        'personal_data' => '/\b((credit card|social security|passport) number|password|address|phone number)\b/i',
        'hate_speech' => '/\b(racial slurs|hate speech|offensive content)\b/i'
    ];

    /**
     * Add safety instructions to a messages array
     *
     * @param array $messages Messages array for the LLM
     * @param string|null $safetyInstruction Custom safety instruction
     * @return array Updated messages array
     */
    public static function addSafetyInstructions(array $messages, ?string $safetyInstruction = null): array
    {
        $instruction = $safetyInstruction ?? self::$defaultSafetyInstruction;

        // Look for an existing system message
        $hasSystem = false;
        foreach ($messages as &$message) {
            if ($message['role'] === 'system') {
                // Append safety instruction to existing system message
                $message['content'] .= "\n\n" . $instruction;
                $hasSystem = true;
                break;
            }
        }

        // If no system message exists, add one
        if (!$hasSystem) {
            array_unshift($messages, [
                'role' => 'system',
                'content' => $instruction
            ]);
        }

        return $messages;
    }

    /**
     * Check if content contains potentially sensitive information
     *
     * @param string $content Content to check
     * @return array Array of warnings or empty array if safe
     */
    public static function checkContent(string $content): array
    {
        $warnings = [];

        foreach (self::$contentWarningPatterns as $type => $pattern) {
            if (preg_match($pattern, $content)) {
                $warnings[] = $type;
            }
        }

        return $warnings;
    }

    /**
     * Filter sensitive information from content
     *
     * @param string $content Content to filter
     * @return string Filtered content
     */
    public static function filterSensitiveInfo(string $content): string
    {
        // Filter common patterns like credit card numbers, emails, etc.
        $patterns = [
            // Credit card numbers
            '/\b(?:\d{4}[ -]?){3}\d{4}\b/' => '[REDACTED CARD]',
            // Email addresses
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/' => '[REDACTED EMAIL]',
            // Phone numbers
            '/\b(?:\+\d{1,2}\s?)?\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}\b/' => '[REDACTED PHONE]',
            // IP addresses
            '/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/' => '[REDACTED IP]'
        ];

        return preg_replace(array_keys($patterns), array_values($patterns), $content);
    }

    /**
     * Set the default safety instruction
     *
     * @param string $instruction New default instruction
     */
    public static function setDefaultSafetyInstruction(string $instruction): void
    {
        self::$defaultSafetyInstruction = $instruction;
    }

    /**
     * Get the current default safety instruction
     *
     * @return string Current default instruction
     */
    public static function getDefaultSafetyInstruction(): string
    {
        return self::$defaultSafetyInstruction;
    }
}
