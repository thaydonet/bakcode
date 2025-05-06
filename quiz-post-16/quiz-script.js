// Hàm xáo trộn mảng (Fisher-Yates shuffle)
function shuffleArray(array) {
  for (let i = array.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [array[i], array[j]] = [array[j], array[i]];
  }
}

// Hàm giải mã giá trị correct
function decodeCorrect(encodedCorrect) {
  if (encodedCorrect.startsWith('ENC:')) {
    return atob(encodedCorrect.substring(4));
  }
  return encodedCorrect;
}

// Hàm mã hóa giá trị correct
function encodeCorrect(correct) {
  return 'ENC:' + btoa(correct);
}

// Hàm xử lý biến số trong JavaScript
function processVariables(text, variables) {
  if (typeof text !== 'string') return text;
  return text.replace(/!([a-zA-Z0-9*]+)(?::(-?\d+):(-?\d+))?!/g, (_, varName, min, max) => {
    min = min ? parseInt(min) : -10;
    max = max ? parseInt(max) : 10;
    const isNonZero = varName.includes('*0');
    varName = varName.replace('*0', '');
    if (!(varName in variables)) {
      let value;
      do {
        value = Math.floor(Math.random() * (max - min + 1)) + min;
      } while (isNonZero && value === 0);
      variables[varName] = value;
    }
    return variables[varName];
  });
}

// Hàm tính toán biểu thức {tinh: bt}
function calculateExpression(text) {
  if (typeof text !== 'string') return text;
  return text.replace(/{tinh:([^}]+)}/g, (_, expr) => {
    try {
      return math.evaluate(expr.trim());
    } catch (e) {
      console.error('Error evaluating expression:', expr, e);
      return 'Error';
    }
  });
}

// Xáo trộn câu hỏi và rút ngẫu nhiên theo số lượng khai báo
function shuffleQuestions(questionsContainer, quizSet) {
  const questions = Array.from(questionsContainer.querySelectorAll('.quiz-box'));
  const singleChoiceTron = quizSet.dataset.singleChoiceTron.split(',');
  const trueFalseTron = quizSet.dataset.trueFalseTron.split(',');
  const shortAnswerTron = quizSet.dataset.shortAnswerTron;
  const singleChoiceLimit = parseInt(quizSet.dataset.singleChoiceSocau) || 0;
  const trueFalseLimit = parseInt(quizSet.dataset.trueFalseSocau) || 0;
  const shortAnswerLimit = parseInt(quizSet.dataset.shortAnswerSocau) || 0;
  let singleChoiceQuestions = questions.filter(q => q.dataset.type === 'single-choice');
  let trueFalseQuestions = questions.filter(q => q.dataset.type === 'true-false');
  let shortAnswerQuestions = questions.filter(q => q.dataset.type === 'short-answer');
  if (singleChoiceTron[0] === 'y') shuffleArray(singleChoiceQuestions);
  if (trueFalseTron[0] === 'y') shuffleArray(trueFalseQuestions);
  if (shortAnswerTron === 'y') shuffleArray(shortAnswerQuestions);
  if (singleChoiceLimit > 0) {
    singleChoiceQuestions = singleChoiceQuestions.slice(0, Math.min(singleChoiceLimit, singleChoiceQuestions.length));
  }
  if (trueFalseLimit > 0) {
    trueFalseQuestions = trueFalseQuestions.slice(0, Math.min(trueFalseLimit, trueFalseQuestions.length));
  }
  if (shortAnswerLimit > 0) {
    shortAnswerQuestions = shortAnswerQuestions.slice(0, Math.min(shortAnswerLimit, shortAnswerQuestions.length));
  }
  const sortedQuestions = [...singleChoiceQuestions, ...trueFalseQuestions, ...shortAnswerQuestions];
  questionsContainer.replaceChildren(...sortedQuestions);
  return sortedQuestions;
}

// Khởi tạo câu hỏi trắc nghiệm đơn chọn
function initializeSingleChoice(question, variables, quizSet) {
  const singleChoiceTron = quizSet.dataset.singleChoiceTron.split(',');
  question.querySelectorAll('.option label').forEach(label => {
    label.innerHTML = calculateExpression(processVariables(label.innerHTML, variables));
  });
  let encodedCorrect = question.dataset.correct;
  let correct = calculateExpression(processVariables(decodeCorrect(encodedCorrect), variables));
  question.dataset.correct = encodeCorrect(correct);
  if (singleChoiceTron[1] === 'y') {
    const options = JSON.parse(question.dataset.options);
    const keys = Object.keys(options);
    const values = keys.map(key => options[key]);
    const originalCorrectValue = options[correct];
    shuffleArray(values);
    const optionsSection = question.querySelector('.options-section');
    optionsSection.innerHTML = '';
    let newCorrect = null;
    keys.forEach((key, index) => {
      const newValue = calculateExpression(processVariables(values[index], variables));
      if (newValue === calculateExpression(processVariables(originalCorrectValue, variables))) {
        newCorrect = key;
      }
      const optionHTML = `<div class="option" style="display: flex; align-items: center;">
                            <input type="radio" name="question_${question.id.replace('quiz-box-', '')}" id="question_${question.id.replace('quiz-box-', '')}_option_${key}" value="${key}" style="margin-right: 5px;">
                            <label for="question_${question.id.replace('quiz-box-', '')}_option_${key}" style="margin: 0;"><strong>${key}.</strong> ${newValue}</label>
                        </div>`;
      optionsSection.insertAdjacentHTML('beforeend', optionHTML);
    });
    if (newCorrect) {
      question.dataset.correct = encodeCorrect(newCorrect);
    }
	 // === THÊM LỆNH GỌI MATHJAX Ở ĐÂY ===
    // Yêu cầu MathJax chỉ quét lại phần optionsSection vừa được cập nhật
    if (typeof MathJax !== 'undefined' && MathJax.Hub) {
      MathJax.Hub.Queue(["Typeset", MathJax.Hub, optionsSection]);
       console.log("MathJax queued for typesetting on optionsSection:", optionsSection); // Log để kiểm tra
    }
    // =====================================  
	  
	}
}

// Khởi tạo câu hỏi đúng/sai
function initializeTrueFalse(question, variables, quizSet) {
  const trueFalseTron = quizSet.dataset.trueFalseTron.split(',');
  question.querySelectorAll('.option-tf-label').forEach(label => {
    label.innerHTML = calculateExpression(processVariables(label.innerHTML, variables));
  });
  let encodedCorrect = question.dataset.correct;
  let correct = calculateExpression(processVariables(decodeCorrect(encodedCorrect), variables));
  question.dataset.correct = encodeCorrect(correct);
  if (trueFalseTron[1] === 'y') {
    const options = JSON.parse(question.dataset.options);
    const originalKeys = Object.keys(options);
    const originalCorrect = correct.split(',');
    const shuffledKeys = [...originalKeys];
    shuffleArray(shuffledKeys);
    const optionElements = question.querySelectorAll('.option-tf');
    const optionLetters = ['a', 'b', 'c', 'd'];
    const newOptions = {};
    const newCorrect = [];
    shuffledKeys.forEach((key, i) => {
      newOptions[originalKeys[i]] = options[key];
      if (originalCorrect.includes(key)) {
        newCorrect.push(originalKeys[i]);
      }
      const label = optionElements[i].querySelector('.option-tf-label');
      label.innerHTML = `${optionLetters[i]}) ${calculateExpression(processVariables(options[key], variables))}`;
      const trueRadio = optionElements[i].querySelector('input[value="true"]');
      const falseRadio = optionElements[i].querySelector('input[value="false"]');
      trueRadio.name = `question_tf_${question.id.replace('quiz-box-tf-', '')}_option_${originalKeys[i]}`;
      falseRadio.name = `question_tf_${question.id.replace('quiz-box-tf-', '')}_option_${originalKeys[i]}`;
    });
    question.dataset.options = JSON.stringify(newOptions);
    question.dataset.correct = encodeCorrect(newCorrect.join(','));
  }
}

// Khởi tạo toàn bộ quiz
function initializeQuiz(quizSet, questions, variables) {
  questions.forEach((question, index) => {
    const questionTitle = question.querySelector('.question-section h5');
    let originalQuestion = questionTitle.innerHTML;
    if (/^Câu \d+: /.test(originalQuestion)) {
      originalQuestion = originalQuestion.replace(/^Câu \d+: /, '');
    }
    questionTitle.innerHTML = `<span class="question-number">Câu ${index + 1}:</span> ${calculateExpression(processVariables(originalQuestion, variables))}`;
    if (question.dataset.type === 'single-choice') {
      initializeSingleChoice(question, variables, quizSet);
    } else if (question.dataset.type === 'true-false') {
      initializeTrueFalse(question, variables, quizSet);
    } else if (question.dataset.type === 'short-answer') {
      const input = question.querySelector('.short-answer-input');
      if (input) input.value = '';
      let encodedCorrect = question.dataset.correct;
      let correct = calculateExpression(processVariables(decodeCorrect(encodedCorrect), variables));
      question.dataset.correct = encodeCorrect(correct);
    }
    const explanation = question.querySelector('.explanation-content');
    if (explanation) explanation.innerHTML = calculateExpression(processVariables(explanation.innerHTML, variables));
  });

}

// Tính điểm
function calculateScore(questions, quizSet) {
  let scorePart1 = 0,
    scorePart2 = 0,
    scorePart3 = 0;
  const singleChoicePoints = parseFloat(quizSet.dataset.singleChoicePoints) || 0.25;
  const trueFalsePoints = parseFloat(quizSet.dataset.trueFalsePoints) || 0.25;
  const shortAnswerPoints = parseFloat(quizSet.dataset.shortAnswerPoints) || 0.5;
  questions.forEach(question => {
    const type = question.dataset.type;
    if (type === 'single-choice') {
      const quizId = question.id.replace('quiz-box-', '');
      const selected = question.querySelector(`input[name="question_${quizId}"]:checked`);
      const encodedCorrect = question.dataset.correct;
      const correct = decodeCorrect(encodedCorrect);
      const options = question.querySelectorAll('.option');
      options.forEach(opt => opt.classList.remove('correct', 'incorrect'));
      if (selected) {
        const selectedOption = selected.closest('.option');
        if (selected.value === correct) {
          scorePart1 += singleChoicePoints;
          selectedOption.classList.add('correct');
        } else {
          selectedOption.classList.add('incorrect');
          options.forEach(opt => {
            if (opt.querySelector('input').value === correct) opt.classList.add('correct');
          });
        }
      } else {
        options.forEach(opt => {
          if (opt.querySelector('input').value === correct) opt.classList.add('correct');
        });
      }
      const explanation = question.querySelector('.explanation');
      explanation.insertAdjacentHTML('afterbegin', `<h5>Đáp án đúng: ${correct}</h5>`);
      explanation.style.display = 'block';
    } else if (type === 'true-false') {
      const quizId = question.id.replace('quiz-box-tf-', '');
      const encodedCorrect = question.dataset.correct;
      const correct = decodeCorrect(encodedCorrect);
      const correctAnswers = correct.split(',');
      const options = question.querySelectorAll('.option-tf');
      let correctCount = 0;
      const totalOptions = options.length;
      let hasAnySelection = false;
      options.forEach(opt => opt.classList.remove('correct', 'incorrect'));
      options.forEach((opt, i) => {
        const key = Object.keys(JSON.parse(question.dataset.options))[i];
        const trueRadio = opt.querySelector(`#question_tf_${quizId}_option_${key}_true`);
        const falseRadio = opt.querySelector(`#question_tf_${quizId}_option_${key}_false`);
        const isCorrect = correctAnswers.includes(key);
        if (trueRadio.checked || falseRadio.checked) {
          hasAnySelection = true;
        }
        if (trueRadio.checked && isCorrect) {
          opt.classList.add('correct');
          correctCount++;
        } else if (falseRadio.checked && !isCorrect) {
          opt.classList.add('correct');
          correctCount++;
        } else if (trueRadio.checked || falseRadio.checked) {
          opt.classList.add('incorrect');
        }
      });
      if (!hasAnySelection) {
        options.forEach((opt, i) => {
          const key = Object.keys(JSON.parse(question.dataset.options))[i];
          const isCorrect = correctAnswers.includes(key);
  if (isCorrect) opt.classList.add('correct');
        });
      }
      let questionScore = 0;
      if (totalOptions === 4) {
        if (correctCount === 4) questionScore = 1.0;
        else if (correctCount === 3) questionScore = 0.5;
        else if (correctCount === 2) questionScore = 0.25;
        else if (correctCount === 1) questionScore = 0.1;
        else questionScore = 0.0;
      } else {
        questionScore = (correctCount / totalOptions) * 1.0;
      }
      scorePart2 += questionScore;
      const explanation = question.querySelector('.explanation');
      explanation.insertAdjacentHTML('afterbegin', `<h5>Đáp án đúng: ${correct}</h5>`);
      explanation.style.display = 'block';
    } else if (type === 'short-answer') {
      const quizId = question.id.replace('quiz-box-sa-', '');
      const userAnswer = question.querySelector(`#short_answer_${quizId}`).value.trim();
      const encodedCorrect = question.dataset.correct;
      const correct = decodeCorrect(encodedCorrect);
      if (userAnswer.toLowerCase() === correct.toLowerCase()) {
        scorePart3 += shortAnswerPoints;
        question.querySelector('.answer-section').classList.add('correct');
      } else {
        question.querySelector('.answer-section').classList.add('incorrect');
      }
      const explanation = question.querySelector('.explanation');
      explanation.insertAdjacentHTML('afterbegin', `<h5>Đáp án đúng: ${correct}</h5>`);
      explanation.style.display = 'block';
    }
  });
  return {
    scorePart1,
    scorePart2,
    scorePart3,
    totalScore: scorePart1 + scorePart2 + scorePart3
  };
}

// Sự kiện khi trang tải xong
document.addEventListener('DOMContentLoaded', () => {
  const quizSets = document.querySelectorAll('.quiz-set');
  quizSets.forEach(quizSet => {
    const variables = {};
    let questionsContainer = quizSet.querySelector('.quiz-questions');
    let questions;
    quizSet.quizTimer = null;
    const hasSingleChoice = questionsContainer.querySelector('[data-type="single-choice"]');
    const hasTrueFalse = questionsContainer.querySelector('[data-type="true-false"]');
    const hasShortAnswer = questionsContainer.querySelector('[data-type="short-answer"]');
    let part1Div, part2Div, part3Div;
    if (hasSingleChoice) {
      part1Div = document.createElement('div');
      part1Div.className = 'quiz-part part-1';
      part1Div.innerHTML = '<h4 class="quiz-section-title">Phần I. Trắc nghiệm đơn chọn</h4>';
      questionsContainer.parentElement.insertBefore(part1Div, questionsContainer);
    }
    if (hasTrueFalse) {
      part2Div = document.createElement('div');
      part2Div.className = 'quiz-part part-2';
      part2Div.innerHTML = '<h4 class="quiz-section-title">Phần II. Đúng/Sai</h4>';
      questionsContainer.parentElement.insertBefore(part2Div, questionsContainer);
    }
    if (hasShortAnswer) {
      part3Div = document.createElement('div');
      part3Div.className = 'quiz-part part-3';
      part3Div.innerHTML = '<h4 class="quiz-section-title">Phần III. Trả lời ngắn</h4>';
      questionsContainer.parentElement.insertBefore(part3Div, questionsContainer);
    }
    if (quizSet.dataset.type !== 'exam') {
      questions = shuffleQuestions(questionsContainer, quizSet);
      initializeQuiz(quizSet, questions, variables);
      questions.forEach(question => {
        if (question.dataset.type === 'single-choice' && part1Div) {
          part1Div.appendChild(question);
        } else if (question.dataset.type === 'true-false' && part2Div) {
          part2Div.appendChild(question);
        } else if (question.dataset.type === 'short-answer' && part3Div) {
          part3Div.appendChild(question);
        }
      });
      questionsContainer.remove();
    } else {
      questionsContainer.style.display = 'none';
    }
    function stopTimer() {
      if (quizSet.quizTimer) {
        console.log("Stopping timer");
        clearInterval(quizSet.quizTimer);
        quizSet.quizTimer = null;
      }
    }
    const submitBtn = quizSet.querySelector('.submit-quiz');
    submitBtn.addEventListener('click', () => {
      stopTimer();
      const { scorePart1, scorePart2, scorePart3, totalScore } = calculateScore(questions, quizSet);
      quizSet.querySelector('.score-part-1').textContent = scorePart1.toFixed(2);
      quizSet.querySelector('.score-part-2').textContent = scorePart2.toFixed(2);
      quizSet.querySelector('.score-part-3').textContent = scorePart3.toFixed(2);
      const totalScoreElement = quizSet.querySelector('.total-score');
      totalScoreElement.textContent = totalScore.toFixed(2);
      const studentNameElement = quizSet.querySelector('.student-name-display');
      if (studentNameElement) {
        studentNameElement.innerHTML = `<span style="color: blue; font-weight: bold;">${studentNameElement.textContent}</span>`;
      }
      quizSet.querySelector('.quiz-score').style.display = 'block';
      submitBtn.style.display = 'none';
      quizSet.querySelector('.retry-quiz').style.display = 'inline-block';
      if (quizSet.dataset.type === 'exam') {
        const studentName = quizSet.querySelector('.student-name-input').value.trim();
        if (!studentName) {
          alert('Vui lòng nhập họ và tên trước khi nộp bài!');
          return;
        }
        studentNameElement.innerHTML = `Họ và tên: ${studentName}`;
        const startTime = new Date(quizSet.dataset.startTime);
        const endTime = new Date();
        const timeDiff = (endTime - startTime) / 1000;
        const minutes = Math.floor(timeDiff / 60);
        const seconds = Math.floor(timeDiff % 60);
        quizSet.querySelector('.quiz-result-message').textContent = `Thời gian hoàn thành: ${minutes} phút ${seconds} giây`;

        // === SỬA ĐỔI Ở ĐÂY: Thu thập danh sách đáp án ===
        const answers = [];
        questions.forEach(question => {
          const type = question.dataset.type;
          const encodedCorrect = question.dataset.correct;
          const correct = decodeCorrect(encodedCorrect);
          
          if (type === 'single-choice') {
            const quizId = question.id.replace('quiz-box-', '');
            const selected = question.querySelector(`input[name="question_${quizId}"]:checked`);
            const answer = {
              question_id: question.id,
              type: 'single-choice',
              answer: selected ? selected.value : '',
              is_correct: selected && selected.value === correct ? 1 : 0
            };
            answers.push(answer);
          } else if (type === 'true-false') {
            const quizId = question.id.replace('quiz-box-tf-', '');
            const correctAnswers = correct.split(',');
            const options = question.querySelectorAll('.option-tf');
            options.forEach((opt, i) => {
              const key = Object.keys(JSON.parse(question.dataset.options))[i];
              const trueRadio = opt.querySelector(`#question_tf_${quizId}_option_${key}_true`);
              const falseRadio = opt.querySelector(`#question_tf_${quizId}_option_${key}_false`);
              const isCorrect = correctAnswers.includes(key);
              let studentAnswer = '';
              let isCorrectAnswer = 0;
              if (trueRadio.checked) {
                studentAnswer = 'true';
                isCorrectAnswer = isCorrect ? 1 : 0;
              } else if (falseRadio.checked) {
                studentAnswer = 'false';
                isCorrectAnswer = !isCorrect ? 1 : 0;
              }
              const answer = {
                question_id: `${question.id}_option_${key}`,
                type: 'true-false',
                answer: studentAnswer,
                is_correct: isCorrectAnswer
              };
              answers.push(answer);
            });
          } else if (type === 'short-answer') {
            const quizId = question.id.replace('quiz-box-sa-', '');
            const userAnswer = question.querySelector(`#short_answer_${quizId}`).value.trim();
            const answer = {
              question_id: question.id,
              type: 'short-answer',
              answer: userAnswer,
              is_correct: userAnswer.toLowerCase() === correct.toLowerCase() ? 1 : 0
            };
            answers.push(answer);
          }
        });
        // === KẾT THÚC SỬA ĐỔI: Thu thập danh sách đáp án ===

        const data = {
          action: 'save_quiz_results',
          quiz_id: quizSet.id,
          score_part_1: scorePart1,
          score_part_2: scorePart2,
          score_part_3: scorePart3,
          total_score: totalScore,
          type: quizSet.dataset.type,
          start_time: quizSet.dataset.startTime || new Date().toISOString(),
          end_time: endTime.toISOString(),
          student_name: studentName,
          post_id: quizData.postId,
          answers: JSON.stringify(answers) // Thêm answers vào data
        };

        jQuery.post(quizData.ajaxurl, data, response => {
          if (response.success) {
            quizSet.querySelector('.quiz-result-message').textContent += ` | Đã lưu kết quả cho ${response.data.student_name}.`;
          } else {
            alert('Lỗi khi lưu kết quả: ' + response.data);
          }
        });
      } else {
        studentNameElement.innerHTML = '';
      }
    });
    const retryBtn = quizSet.querySelector('.retry-quiz');
    retryBtn.addEventListener('click', () => {
      window.location.reload();
    });
    if (quizSet.dataset.type === 'exam') {
      const startBtn = quizSet.querySelector('.start-quiz');
      startBtn.addEventListener('click', () => {
        const studentName = quizSet.querySelector('.student-name-input').value.trim();
        if (!studentName) {
          alert('Vui lòng nhập họ và tên trước khi bắt đầu!');
          return;
        }
        quizSet.dataset.startTime = new Date().toISOString();
        startBtn.style.display = 'none';
        questionsContainer.style.display = 'block';
        submitBtn.style.display = 'inline-block';
        questions = shuffleQuestions(questionsContainer, quizSet);
        initializeQuiz(quizSet, questions, variables);
        questions.forEach(question => {
          if (question.dataset.type === 'single-choice' && part1Div) {
            part1Div.appendChild(question);
          } else if (question.dataset.type === 'true-false' && part2Div) {
            part2Div.appendChild(question);
          } else if (question.dataset.type === 'short-answer' && part3Div) {
            part3Div.appendChild(question);
          }
        });
        questionsContainer.remove();
        const timerDisplay = quizSet.querySelector('.quiz-timer');
        timerDisplay.style.display = 'block';
        let timeLeft = parseInt(quizSet.dataset.time) * 60;
        stopTimer();
        quizSet.quizTimer = setInterval(() => {
          if (timeLeft <= 0) {
            stopTimer();
            submitBtn.click();
          } else {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerDisplay.querySelector('span').textContent = `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
            timeLeft--;
          }
        }, 1000);
      });
    }
  });
});
