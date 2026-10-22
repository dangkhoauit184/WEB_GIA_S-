/**
 * Schedule Controller (Thời khóa biểu & Ca học)
 */

exports.getSessions = (req, res) => {
  res.json({
    success: true,
    message: 'Danh sách buổi học theo thời khóa biểu (BR01 - BR03)',
    data: [
      {
        id: 'SES-01',
        classCode: 'LOP-T9A',
        className: 'Toán 9 (Kèm 1-1)',
        subject: 'Toán',
        date: '2026-10-20',
        startTime: '18:00',
        endTime: '20:00',
        room: 'Tại nhà HV (Thủ Đức)',
        mainTeacher: 'Nguyễn Minh Anh',
        status: 'completed'
      }
    ]
  });
};

exports.createSession = (req, res) => {
  const { classCode, date, startTime, endTime } = req.body;
  if (!classCode || !date || !startTime || !endTime) {
    return res.status(400).json({ success: false, message: 'Thiếu thông tin bắt buộc của buổi học!' });
  }
  res.status(201).json({
    success: true,
    message: 'Đã tạo buổi học mới thành công',
    data: { id: 'SES-' + Date.now(), ...req.body }
  });
};

