// ===============================================================
// PHẦN CẤU HÌNH - BẠN CẦN THAY ĐỔI CÁC GIÁ TRỊ DƯỚI ĐÂY
// ===============================================================

// 1. API Key của Google Gemini
const GEMINI_API_KEY = 'AIzaSyC9zlAMGJK3YZIlbNqUbz1P9SPbH0az8m0'; // <-- THAY THẾ BẰNG API KEY CỦA BẠN

// 2. Thông tin WordPress
const WP_URL = 'https://hoc.io.vn'; // <-- THAY BẰNG TÊN MIỀN CỦA BẠN
const WP_USERNAME = 'admin';   // <-- THAY BẰNG USERNAME ĐĂNG NHẬP WP
const WP_APP_PASSWORD = 'rTfv u8Di HsHx oomc rqG3 SMHp'; // <-- THAY BẰNG MẬT KHẨU ỨNG DỤNG

// 3. Chuyên mục và Thẻ cho bài viết (XEM HƯỚNG DẪN BÊN DƯỚI ĐỂ LẤY ID)
const WP_CATEGORY_IDS = [5]; // <-- THAY BẰNG ID CHUYÊN MỤC CỦA BẠN, ví dụ: [12] hoặc [12, 15]
const WP_TAG_IDS = [6,9];      // <-- THAY BẰNG ID THẺ CỦA BẠN, ví dụ: [25, 31]

// 4. Tên trang tính và cột bạn muốn theo dõi
const SHEET_NAME_TO_WATCH = 'mcq'; // <-- THAY BẰNG TÊN SHEET CỦA BẠN (ví dụ: 'Trang tính1')
const COLUMN_TO_WATCH = 1; // Cột A (Cột nhập câu hỏi gốc)
const START_COLUMN_TO_WRITE = 2; // Bắt đầu ghi câu hỏi mới từ cột B
const NUMBER_OF_QUESTIONS = 10; // Số lượng câu hỏi cần tạo
const STATUS_COLUMN = START_COLUMN_TO_WRITE + NUMBER_OF_QUESTIONS; // Cột L (B-K là 10 câu, L là trạng thái)

// ===================================================================================
// HÀM TỰ ĐỘNG HÓA KHI CHỈNH SỬA (TRIGGER FUNCTION)
// ===================================================================================

/**
 * Hàm này sẽ được kích hoạt tự động mỗi khi có chỉnh sửa trong trang tính.
 * @param {Object} e - Đối tượng sự kiện được cung cấp bởi Google.
 */
function handleEdit(e) {
  const range = e.range;
  const sheet = range.getSheet();
  
  if (sheet.getName() === SHEET_NAME_TO_WATCH && range.getColumn() === COLUMN_TO_WATCH && e.value) {
    const currentRow = range.getRow();
    const cauHoiGoc = e.value;
    
    // Ghi trạng thái ban đầu
    const statusCell = sheet.getRange(currentRow, STATUS_COLUMN);
    statusCell.setValue("⏳ Đang xử lý với AI...");
    SpreadsheetApp.flush();

    // Gọi hàm chính để thực hiện toàn bộ quy trình
    const result = processNewQuestion(cauHoiGoc, sheet, currentRow);
    
    // Cập nhật trạng thái cuối cùng
    statusCell.setValue(result);
  }
}

// ===================================================================================
// CÁC HÀM XỬ LÝ CHÍNH
// ===================================================================================

/**
 * Hàm tổng hợp: Gọi AI, điền vào Sheet, và đăng bài lên WordPress.
 * @param {string} originalQuestion Câu hỏi gốc.
 * @param {GoogleAppsScript.Spreadsheet.Sheet} sheet Trang tính đang hoạt động.
 * @param {number} row Dòng đang được chỉnh sửa.
 * @return {string} Thông báo kết quả cuối cùng.
 */
function processNewQuestion(originalQuestion, sheet, row) {
  // 1. Tạo prompt nâng cao và gọi Gemini API
  const prompt = `Với vai trò là một chuyên gia ra đề thi Toán 12, hãy thực hiện các yêu cầu sau dựa trên câu hỏi mẫu.

YÊU CẦU:
1.  **Phân tích câu hỏi mẫu:** Nêu rõ dạng bài, kiến thức liên quan, mức độ (Nhận biết, Thông hiểu, Vận dụng, Vận dụng cao) và phương pháp giải chi tiết (mỗi phần xuống dòng, in đậm tiêu đề).
2.  **Tạo ${NUMBER_OF_QUESTIONS} câu hỏi tương tự:** Tạo chính xác ${NUMBER_OF_QUESTIONS} câu hỏi trắc nghiệm mới, mỗi câu phải có đủ 4 đáp án A. B. C. D. được in đậm và xuống dòng và lời giải chi tiết.
với 8 câu đầu: Tương tự về dạng và độ khó, chỉ thay đổi số liệu hoặc ngữ cảnh như (đồng biến, nghịch biến,...).
và  2 câu cuối: Tương tự về dạng và Nâng cao độ khó lên 1 bậc không cho tham số m.
3.  **Định dạng:** Giữ nguyên định dạng Latex. Đánh dấu sao (*) vào trước đáp án đúng. Mọi tiêu đề phải được in đậm bằng thẻ <b> (ví dụ: <b>Câu 1:</b>, <b>Lời giải:</b>).

ĐỊNH DẠNG ĐẦU RA (TUÂN THỦ NGHIÊM NGẶT, dùng các dấu phân cách đặc biệt):
[Nội dung phần phân tích]###ANALYSIS###[Nội dung câu 1]|||---|||[Nội dung câu 2]|||---|||... (và tiếp tục cho đến hết ${NUMBER_OF_QUESTIONS} câu)

Câu hỏi mẫu:
${originalQuestion}`;

  const geminiResponseText = callGeminiAPI(prompt);
  const formattedResponse = geminiResponseText.replace(/\*\*(.*?)\*\*/g, '<b>$1</b>');

  if (formattedResponse.startsWith("Lỗi")) {
    sheet.getRange(row, START_COLUMN_TO_WRITE).setValue(formattedResponse);
    return formattedResponse;
  }

  // 2. Phân tích và trích xuất thông tin từ kết quả của AI
  try {
    const parts = formattedResponse.split('###ANALYSIS###');
    if (parts.length < 2) throw new Error("AI không trả về đúng định dạng có '###ANALYSIS###'.");
    
    const analysis = parts[0].trim();
    const questionsString = parts[1];
    const questionsArray = questionsString.split('|||---|||').map(q => q.trim().replace(/^\[|\]$/g, ''));
    
    if (questionsArray.length < NUMBER_OF_QUESTIONS) {
       throw new Error(`AI chỉ trả về ${questionsArray.length}/${NUMBER_OF_QUESTIONS} câu hỏi.`);
    }

    // 3. Điền 10 câu hỏi đã tạo vào các ô trên Google Sheet
    const questionsToWrite = questionsArray.slice(0, NUMBER_OF_QUESTIONS);
    sheet.getRange(row, START_COLUMN_TO_WRITE, 1, NUMBER_OF_QUESTIONS).setValues([questionsToWrite]);
    
    // 4. Chuẩn bị nội dung và đăng bài lên WordPress
    const title = originalQuestion.split('\n')[0].trim(); // Lấy dòng đầu của câu hỏi gốc làm tiêu đề
    
    let postContent = `<h2 style="color:red;"><b>Câu hỏi mẫu</b></h2>\n<div class="latex_thm">${originalQuestion.replace(/\n/g, '<br>')}</div>\n<hr>\n`;
    postContent += `<h2 style="color:red;"><b>Phân tích câu hỏi mẫu</b></h2>\n<p>${analysis.replace(/\n/g, '<br>')}</p>\n<hr>\n`;
    postContent += `<h2 style="color:red;"><b>Các câu hỏi tương tự</b></h2>\n`;
    
    questionsToWrite.forEach((question, index) => {
        postContent += `<p>${question.replace(/\n/g, '<br>')}</p>\n`;
    });

    return postToWordPress(title, postContent, WP_CATEGORY_IDS, WP_TAG_IDS);

  } catch (e) {
    Logger.log("Phản hồi gốc từ AI gây lỗi: " + formattedResponse);
    return "Lỗi khi xử lý: " + e.toString();
  }
}

/**
 * Gọi API của Google Gemini.
 * @param {string} prompt Câu lệnh cho AI.
 * @return {string} Phản hồi dạng text từ AI hoặc thông báo lỗi.
 */
function callGeminiAPI(prompt) {
  const url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=" + GEMINI_API_KEY;
  const requestBody = {"contents": [{"parts": [{"text": prompt}]}]};
  const options = {'method': 'post', 'contentType': 'application/json', 'payload': JSON.stringify(requestBody), 'muteHttpExceptions': true};
  
  const maxRetries = 4; // Thử lại tối đa 4 lần
  let lastError = "";

  for (let i = 0; i < maxRetries; i++) {
    try {
      const response = UrlFetchApp.fetch(url, options);
      const responseCode = response.getResponseCode();
      const responseBody = response.getContentText();

      if (responseCode === 200) {
        if (responseBody && JSON.parse(responseBody).candidates[0].content.parts[0].text) {
          return JSON.parse(responseBody).candidates[0].content.parts[0].text.trim();
        }
        lastError = "Lỗi: Phản hồi từ API không có nội dung. " + responseBody;
      } else if (responseCode === 503 || responseCode === 429) {
        // 503: Quá tải, 429: Quá nhiều yêu cầu -> Lỗi tạm thời, cần thử lại
        lastError = `Lỗi từ API Gemini: ${responseCode} - Model đang bận. Đang thử lại...`;
        Logger.log(lastError + ` (lần ${i + 1})`);
        // Chờ đợi theo cấp số nhân trước khi thử lại
        Utilities.sleep(Math.pow(2, i) * 1000 + Math.random() * 1000); 
      } else {
        // Các lỗi khác (400, 500, ...) là lỗi nghiêm trọng, không cần thử lại
        return `Lỗi từ API Gemini: ${responseCode} - ${responseBody}`;
      }
    } catch (e) {
      lastError = "Lỗi khi gọi API Gemini: " + e.toString();
      Logger.log(lastError + ` (lần ${i + 1})`);
      Utilities.sleep(Math.pow(2, i) * 1000 + Math.random() * 1000);
    }
  }
  // Nếu tất cả các lần thử lại đều thất bại
  return lastError;
}

/**
 * Gửi yêu cầu POST để tạo bài viết mới trên WordPress.
 * @param {string} title Tiêu đề bài viết.
 * @param {string} content Nội dung bài viết (đã định dạng HTML).
 * @param {number[]} categoryIds Mảng các ID của chuyên mục.
 * @param {number[]} tagIds Mảng các ID của thẻ.
 * @return {string} Thông báo kết quả.
 */
function postToWordPress(title, content, categoryIds, tagIds) {
  const apiUrl = WP_URL + '/wp-json/wp/v2/posts';
  const encodedAuth = Utilities.base64Encode(WP_USERNAME + ':' + WP_APP_PASSWORD);
  
  const postData = {
    'title': title,
    'content': content,
    'status': 'publish',
    'categories': categoryIds,
    'tags': tagIds
  };

  const options = {
    'method': 'post',
    'contentType': 'application/json',
    'headers': {'Authorization': 'Basic ' + encodedAuth},
    'payload': JSON.stringify(postData),
    'muteHttpExceptions': true
  };

  try {
    const response = UrlFetchApp.fetch(apiUrl, options);
    const responseCode = response.getResponseCode();
    const responseBody = response.getContentText();
    if (responseCode === 201) {
      const json = JSON.parse(responseBody);
      return '✅ Đăng thành công! Link: ' + json.link;
    } else {
      Logger.log(responseBody);
      return '❌ Lỗi WordPress: ' + responseCode + '. Chi tiết trong Logs.';
    }
  } catch (e) {
    return '❌ Lỗi kết nối WordPress: ' + e.toString();
  }
}
