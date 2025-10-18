// **الكود النهائي والمعدّل لـ Vercel / Node.js**

const { Telegraf } = require('telegraf');
const axios = require('axios');

// توكن البوت
const BOT_TOKEN = '8089342552:AAFITpAd2kI_zTFnuTiRgz20Na3tyHI2z1o'; 
const YOUTUBE_API_KEY = 'AIzaSyArghTT46ER-KxypZp0R6W0wjcUdYWe-zw'; 

// **المفاتيح النهائية الصحيحة**
const RAPIDAPI_KEY = '37a01cb857fmshed562dc8a5ae19cp176145jsn2a05b73ca17e'; // <== تم الإدخال 
const RAPIDAPI_HOST = 'youtube-to-mp3-converter2.p.rapidapi.com'; // <== تم الإدخال
const CONVERSION_ENDPOINT = `https://${RAPIDAPI_HOST}/dl`;

const bot = new Telegraf(BOT_TOKEN);

// دالة البحث عن فيديو يوتيوب
async function searchYouTube(query) {
    try {
        const searchUrl = `https://www.googleapis.com/youtube/v3/search?part=snippet&q=${encodeURIComponent(query)}&type=video&maxResults=1&key=${YOUTUBE_API_KEY}`;
        const response = await axios.get(searchUrl);
        const item = response.data.items[0];
        if (item) {
            return {
                title: item.snippet.title,
                videoId: item.id.videoId,
                videoUrl: `https://www.youtube.com/watch?v=${item.id.videoId}` 
            };
        }
    } catch (error) {
        console.error('YouTube Search Error:', error.message);
    }
    return null;
}

// دالة التحويل إلى MP3
async function convertToMp3(videoUrl) {
    const postData = {
        url: videoUrl,
        q: 'mp3' 
    };

    const headers = {
        'X-RapidAPI-Key': RAPIDAPI_KEY,
        'X-RapidAPI-Host': RAPIDAPI_HOST,
        'Content-Type': 'application/json'
    };

    try {
        const response = await axios.post(CONVERSION_ENDPOINT, postData, { headers, timeout: 90000 });
        const mp3Data = response.data;
        
        if (mp3Data.link) return mp3Data.link;
        if (mp3Data.file) return mp3Data.file;

    } catch (error) {
        console.error('RapidAPI Conversion Error:', error.message);
    }
    return null;
}

// معالجة رسائل المستخدم
bot.on('text', async (ctx) => {
    const messageText = ctx.message.text.trim();
    let query = '';

    const lowerCaseText = messageText.toLowerCase();
    if (ctx.chat.type === 'group' || ctx.chat.type === 'supergroup') {
        if (lowerCaseText.startsWith('يوت')) {
            query = messageText.substring(3).trim();
        } else if (lowerCaseText.startsWith('yt')) {
            query = messageText.substring(2).trim();
        } else {
            return; 
        }
    } else {
        query = messageText;
    }

    if (!query) {
        return ctx.reply("الرجاء كتابة أمر البحث.\n\nمثال: `يوت اسم الأغنية`", { reply_to_message_id: ctx.message.message_id });
    }

    const initialMessage = await ctx.reply(`جاري البحث عن **${query}** وتحويلها إلى MP3، يرجى الانتظار...`, { parse_mode: 'Markdown', reply_to_message_id: ctx.message.message_id });

    try {
        const videoInfo = await searchYouTube(query);

        if (!videoInfo) {
            return ctx.editMessageText(initialMessage.message_id, initialMessage.chat.id, `لم يتم العثور على نتائج لـ **${query}** أو حدث خطأ في الاتصال بواجهة يوتيوب.`, { parse_mode: 'Markdown' });
        }

        const mp3Link = await convertToMp3(videoInfo.videoUrl);

        if (!mp3Link) {
            return ctx.editMessageText(initialMessage.message_id, initialMessage.chat.id, 'عفواً، فشل التحويل. قد تكون واجهة RapidAPI متوقفة حالياً أو المفاتيح غير صحيحة.', { parse_mode: 'Markdown' });
        }

        const captionText = `**${videoInfo.title}**\n_تم التحويل عبر Vercel_`;

        // إرسال الصوتية
        await ctx.replyWithAudio(mp3Link, {
            caption: captionText,
            title: videoInfo.title,
            reply_to_message_id: ctx.message.message_id
        });

        // حذف رسالة "جاري البحث..."
        await ctx.deleteMessage(initialMessage.message_id);

    } catch (error) {
        console.error('Final Error:', error.message);
        ctx.editMessageText(initialMessage.message_id, initialMessage.chat.id, 'حدث خطأ غير متوقع أثناء المعالجة.', { parse_mode: 'Markdown' });
    }
});

// تصدير كدالة Vercel Serverless
module.exports = async (req, res) => {
    try {
        await bot.handleUpdate(req.body, res);
    } catch (err) {
        console.error(err);
        res.status(200).send('OK'); 
    }
};
