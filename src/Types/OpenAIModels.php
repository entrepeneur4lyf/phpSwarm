<?php

declare(strict_types=1);

namespace phpSwarm\Types;

/**
 * Enum OpenAIModels
 * 
 * Represents the various OpenAI models available for use in the Swarm system.
 */
enum OpenAIModels
{
    case GPT4O;
    case GPT40MINI;
    case GPT4RTP;
    case GPT40AP;
    case O1P;
    case O1MINI;
    case DALLE3;
    case TE3L;
    case TE3S;
    case TTS;
    case TTSHD;
    case OMOD;
    
    /**
     * Get the string representation of the OpenAI model.
     *
     * @return string The string identifier for the OpenAI model.
     */
    public function model(): string
    {
        return match($this) 
        {
            OpenAIModels::GPT4O => 'gpt-4o',
            OpenAIModels::GPT40MINI => 'gpt-4o-mini',
            OpenAIModels::GPT4RTP => 'gpt-4o-realtime-preview',
            OpenAIModels::GPT40AP => 'gpt-4o-audio-preview',
            OpenAIModels::O1P => 'o1-preview',
            OpenAIModels::O1MINI => 'o1-mini',
            OpenAIModels::DALLE3 => 'dall-e-3',
            OpenAIModels::TE3L => 'text-embedding-3-large',
            OpenAIModels::TE3S => 'text-embedding-3-small',
            OpenAIModels::TTS => 'tts-1',
            OpenAIModels::TTSHD => 'tts-1-hd',
            OpenAIModels::OMOD => 'omni-moderation-latest'
        };
    }
}
