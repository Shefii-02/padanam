import '../../../core/utils/json.dart';

/// /auth/me (MeResource) – works for students and staff/teachers.
class AppUser {
  AppUser(this.json);
  final Json json;

  int get id => json.i('id');
  String get phone => json.s('phone');
  String get name => json.s('name').isEmpty ? 'Student' : json.s('name');
  String get firstName => name.trim().split(RegExp(r'\s+')).first;
  String get initials => json.s('initials', 'ME');
  String get avatar => json.s('avatar', '🧑‍🎓');
  String? get photoUrl => json.sn('photo_url');
  String? get district => json.sn('district');
  int? get age => json['age'] == null ? null : json.i('age');
  String get language => json.s('language', 'ml');
  List<String> get exams => json.ls('exams');
  Json get targetPosts => json.m('profile').m('target_posts');
  bool get profileCompleted => json.b('profile_completed') || (json.s('name').isNotEmpty && !json.b('is_new_user'));
  String get primaryExam => exams.isEmpty ? 'kerala-psc' : exams.first;
  String get role => json.s('role', 'student');
  List<String> get permissions => json.ls('permissions');
  bool get isTeacher => json.b('is_teacher') || role == 'teacher';
  bool get isStaff => json.b('is_panel_user');
  bool can(String perm) => permissions.contains('*') || permissions.contains(perm);
  String? get referralCode => json.sn('referral_code');
}
